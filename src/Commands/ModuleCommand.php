<?php

namespace NickDeKruijk\Leap\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Eloquent\Model;
use NickDeKruijk\Leap\Classes\ModuleGenerator;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ModuleCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leap:module
        {model? : Model name (Event) or FQCN (App\Models\Event)}
        {--name= : Leap class name, defaults to the model\'s basename}
        {--icon= : blade-icon name, e.g. fas-calendar-days}
        {--force : Fully regenerate the module file instead of merging in new columns}
        {--dry-run : Print the generated/merged code without writing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate (or update) a Leap module from an Eloquent model';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // A scaffolding command — never on production without --force.
        if (! $this->confirmToProceed()) {
            return 1;
        }

        $modelClass = $this->resolveModelClass();
        if (! $modelClass) {
            return 1;
        }

        $className = $this->option('name') ?: class_basename($modelClass);
        $path = app_path('Leap/'.$className.'.php');

        $generator = new ModuleGenerator($modelClass);
        $columnPlans = $generator->columnPlans();
        $interactive = $this->input->isInteractive();

        if (file_exists($path) && ! $this->option('force')) {
            return $this->merge($generator, $path, $columnPlans, $interactive);
        }

        return $this->generate($generator, $className, $path, $columnPlans, $interactive);
    }

    /**
     * Resolve the `model` argument (bare name or FQCN) to an existing Eloquent
     * model class, prompting for it when omitted.
     */
    protected function resolveModelClass(): ?string
    {
        $model = $this->argument('model') ?: text(
            label: 'Model name (e.g. Event) or FQCN (App\Models\Event)',
            required: true,
        );

        $class = str_contains($model, '\\') ? ltrim($model, '\\') : 'App\Models\\'.$model;

        if (! class_exists($class)) {
            $this->error("Model class {$class} not found. Create it first, e.g.: php artisan make:model ".class_basename($class).' -m');

            return null;
        }

        if (! is_subclass_of($class, Model::class)) {
            $this->error("{$class} is not an Eloquent model.");

            return null;
        }

        return $class;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columnPlans
     */
    protected function generate(ModuleGenerator $generator, string $className, string $path, array $columnPlans, bool $interactive): int
    {
        $modulePlan = $generator->modulePlan();

        if ($this->option('icon')) {
            $modulePlan['icon'] = $this->option('icon');
        }

        if ($interactive) {
            $modulePlan['icon'] = text('Icon (blade-icon name)', default: $modulePlan['icon']);
            $modulePlan['priority'] = (int) text('Navigation priority (lower = higher in the menu)', default: (string) $modulePlan['priority']);

            $booleanColumns = array_column(array_filter($columnPlans, fn ($plan) => $plan['baseType'] === 'switch'), 'name');
            if ($booleanColumns) {
                $modulePlan['activeColumn'] = select(
                    label: 'Which column marks a row as active/inactive? (shown struck-through when off)',
                    options: array_merge(['' => '(none)'], array_combine($booleanColumns, $booleanColumns)),
                    default: $modulePlan['activeColumn'] ?: '',
                ) ?: null;
            }

            $dateColumns = array_column(array_filter($columnPlans, fn ($plan) => in_array($plan['baseType'], ['date', 'datetime'])), 'name');
            if (! $modulePlan['sortColumn'] && $dateColumns) {
                $modulePlan['orderByColumn'] = select(
                    label: 'Default sort column for the index',
                    options: array_merge(['' => '(none, use natural order)'], array_combine($dateColumns, $dateColumns)),
                    default: $modulePlan['orderByColumn'] ?: '',
                ) ?: null;
                if ($modulePlan['orderByColumn']) {
                    $modulePlan['orderDesc'] = confirm('Sort descending (newest/soonest first)?', default: $modulePlan['orderDesc']);
                }
            }

            $columnPlans = array_map(fn ($plan) => $this->promptColumn($plan), $columnPlans);
        }

        $source = $generator->renderClass($className, $columnPlans, $modulePlan);

        if ($this->option('dry-run')) {
            $this->line($source);

            return 0;
        }

        if (! is_dir(app_path('Leap'))) {
            mkdir(app_path('Leap'), 0777, true);
        }

        file_put_contents($path, $source);
        $this->info("Created app/Leap/{$className}.php");
        $this->printNextSteps($generator, $columnPlans);

        return 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columnPlans
     */
    protected function merge(ModuleGenerator $generator, string $path, array $columnPlans, bool $interactive): int
    {
        $contents = file_get_contents($path);
        $relative = 'app/Leap/'.basename($path);

        if (! ModuleGenerator::looksMergeable($contents)) {
            $this->error("{$relative} doesn't look like a Resource with a plain attributes() array — can't merge safely. Rerun with --force to replace it, or add the column manually.");

            return 1;
        }

        $existing = ModuleGenerator::existingColumns($contents);
        $newPlans = array_values(array_filter($columnPlans, fn ($plan) => ! in_array($plan['name'], $existing)));

        if (! $newPlans) {
            $this->info("{$relative} is already up to date — no new columns detected.");

            return 0;
        }

        if ($interactive) {
            $newPlans = array_map(fn ($plan) => $this->promptColumn($plan), $newPlans);
        }

        $locales = config('leap.locales');
        $lines = array_map(fn ($plan) => $generator->renderAttributeLine($plan, $locales), $newPlans);
        $columnNames = implode(', ', array_column($newPlans, 'name'));

        if ($this->option('dry-run')) {
            $this->info("Would add to {$relative}: {$columnNames}");
            foreach ($lines as $line) {
                $this->line('    '.$line);
            }

            return 0;
        }

        if ($interactive && ! confirm("Add {$columnNames} to {$relative}?", default: true)) {
            return 0;
        }

        $patched = ModuleGenerator::insertAttributes($contents, $lines);
        if ($patched === null) {
            $this->error("Couldn't locate the attributes() return array in {$relative} to patch it. Rerun with --force, or add manually:");
            foreach ($lines as $line) {
                $this->line('    '.$line);
            }

            return 1;
        }

        file_put_contents($path, $patched);
        $this->info("Added {$columnNames} to {$relative}");

        return 0;
    }

    /**
     * Let the user confirm/override a detected column's type, required flag and
     * label. Never asked blind — every prompt default is the auto-detected value.
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    protected function promptColumn(array $plan): array
    {
        if ($plan['indexOnly']) {
            return $plan;
        }

        $editableTypes = ['text', 'textarea', 'richtext', 'number', 'date', 'datetime', 'time', 'email', 'password', 'switch', 'json'];

        if (in_array($plan['baseType'], $editableTypes)) {
            $plan['baseType'] = select(
                label: "Field type for '{$plan['name']}'",
                options: array_combine($editableTypes, $editableTypes),
                default: $plan['baseType'],
            );
        } else {
            $this->line("Field '{$plan['name']}': {$plan['baseType']} (auto-detected from the schema)");
        }

        $plan['required'] = confirm("Is '{$plan['name']}' required?", default: $plan['required']);
        $plan['label'] = $this->promptLabel("Label for '{$plan['name']}'", $plan['label']);

        return $plan;
    }

    /**
     * Ask for a label. When the app is running in a non-English locale and Leap
     * has per-locale labels configured, ask for the label in that locale
     * specifically (defaulting to the humanized/generated text as a starting
     * point) and keep the generated text as the 'en' entry, rather than storing
     * an English guess under the developer's own locale.
     *
     * @return string|array<string, string>
     */
    protected function promptLabel(string $question, string $generated): string|array
    {
        $locale = app()->getLocale();
        $locales = config('leap.locales');

        if (! $locales || $locale === 'en') {
            return text($question, default: $generated);
        }

        $translated = text("{$question} ({$locale})", default: $generated);

        $labels = array_fill_keys(array_keys($locales), $generated);
        $labels['en'] = $generated;
        $labels[$locale] = $translated;

        return $labels;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columnPlans
     */
    protected function printNextSteps(ModuleGenerator $generator, array $columnPlans): void
    {
        $reminders = [];

        foreach ($columnPlans as $plan) {
            if ($plan['baseType'] === 'switch') {
                $reminders[] = "'{$plan['name']}' => 'boolean'";
            }
        }

        if ($reminders) {
            $this->newLine();
            $this->line('Make sure the model casts these columns, so switches store real booleans:');
            $this->line('  protected $casts = ['.implode(', ', array_unique($reminders)).'];');
        }

        $translatable = $generator->translatableColumns();
        if ($translatable) {
            $this->newLine();
            $this->line('These columns are already translatable on the model ('.implode(', ', $translatable).') — Leap edits them per locale automatically, nothing to change.');
        }
    }
}
