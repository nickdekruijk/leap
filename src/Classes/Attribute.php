<?php

namespace NickDeKruijk\Leap\Classes;

use Exception;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Module;

class Attribute
{
    public static $_instance = null;

    public string $dataName;
    public string $name;
    public array $options = [];
    public ?int $index;
    public bool $indexOnly = false;
    public ?string $input = 'input';
    public ?int $rows = null;
    public string|null $label;
    public string $labelIndex;
    public string $placeholder = '';
    public ?string $role = null;
    public bool $searchable = false;
    public ?string $slugify = null;
    public ?int $step = null;
    public string $type = 'text';
    public ?string $confirmed = null;
    public array $validate = [];
    public array $values = [];
    public string $wire = 'blur';
    public array $sections;
    public string $sectionName = '';
    public bool $isAccessor = false;
    public array $accessorColumns;
    public bool $disabled = false;
    public bool $filterable = false;
    public mixed $default = null;
    public bool $hidden = false;

    /**
     * Make a new Attribute instance and set default label based on name.
     *
     * @param string $name
     * @return Attribute
     */
    public static function make(string $name): Attribute
    {
        self::$_instance = new self;
        self::$_instance->dataName = 'data.' . $name;
        self::$_instance->name = $name;

        self::$_instance->label = self::$_instance->labelIndex = trans(Str::headline($name));

        return self::$_instance;
    }

    /**
     * Make the Attribute a date
     *
     * @return Attribute
     */
    public function date(): Attribute
    {
        $this->type = 'date';
        $this->validate('date');
        return $this;
    }

    /**
     * Make the Attribute a datetime
     *
     * @param boolean $includeSeconds Include seconds input when editing
     * @return Attribute
     */
    public function datetime(bool $includeSeconds = false): Attribute
    {
        $this->type = 'datetime-local';
        $this->validate('date');
        if ($includeSeconds) {
            $this->step = 1;
        }
        return $this;
    }

    /**
     * Make the Attribute a time
     *
     * @param boolean $includeSeconds Include seconds input when editing
     * @return Attribute
     */
    public function time(bool $includeSeconds = false): Attribute
    {
        $this->type = 'time';
        $this->validate('dateformat:H:i' . ($includeSeconds ? ':s' : ''));
        if ($includeSeconds) {
            $this->step = 1;
        }
        return $this;
    }

    /**
     * Make the Attribute an email
     * 
     * The type will be set to email and a validation rules 'email' will be added.
     *
     * @param string $validator The validator to use for example 'strict,dns,spoof' or any combination of rfc, strict, dns, spoof, filter, filter_unicode. The dns and spoof validators require the PHP intl extension.
     * @return Attribute
     */
    public function email(string $validator = 'strict'): Attribute
    {
        $this->type = 'email';
        $this->validate('email:' . $validator);
        return $this;
    }

    /**
     * Make the Attribute a checkbox
     * 
     * The attribute must be set to boolean in the model $casts definition to work properly.
     * 
     * @return Attribute
     */
    public function checkbox(): Attribute
    {
        $this->type = 'checkbox';
        return $this;
    }

    /**
     * Make the Attribute a checkbox with an extra role="switch" attribute
     * 
     * The attribute must be set to boolean in the model $casts definition to work properly.
     * 
     * @return Attribute
     */
    public function switch(): Attribute
    {
        $this->type = 'checkbox';
        $this->role = 'switch';
        return $this;
    }

    /**
     * Make the Attribute a list of radio buttons
     * 
     * Values must be set with the values() method, e.g. ->values([1 => 'Option 1', 2 => 'Option 2']) or ->values(['Option 1', 'Option 2'])
     *
     * @param boolean $group When true the radio buttons will be grouped on a single line
     * @return Attribute
     */
    public function radio(bool $group = false): Attribute
    {
        $this->options['group'] = $group;
        $this->input = 'radio';
        return $this;
    }

    /**
     * Make the Attribute a select element
     * 
     * Values must be set with the values() method, e.g. ->values([1 => 'Option 1', 2 => 'Option 2']) or ->values(['Option 1', 'Option 2'])
     *
     * @return Attribute
     */
    public function select(): Attribute
    {
        $this->input = 'select';
        return $this;
    }

    /**
     * Make the Attribute a file element
     * 
     * Allows you to select a singl files from the FileManager
     *
     * @return Attribute
     */
    public function file(): Attribute
    {
        $this->input = 'files';
        $this->options['multiple'] = false;
        return $this;
    }

    /**
     * Make the Attribute a files element
     * 
     * Allows you to select multiple files from the FileManager
     *
     * @return Attribute
     */
    public function files(): Attribute
    {
        $this->input = 'files';
        $this->options['multiple'] = true;
        return $this;
    }

    /**
     * Make the Attribute a media element
     * 
     * Allows you to select multiple media files from the FileManager
     * @param boolean $multiple Whether multiple files can be selected
     * @param boolean $ignoreOrganizationPrefix Whether to ignore the organization prefix when selecting files
     *
     * @return Attribute
     */
    public function media($multiple = true, bool $ignoreOrganizationPrefix = false): Attribute
    {
        if ($this->name == 'media') {
            throw new Exception('The media attribute cannot be named "media"');
        }
        $this->type = 'media';
        $this->input = 'media';
        $this->options['media'] = true;
        $this->options['ignoreOrganizationPrefix'] = $ignoreOrganizationPrefix;
        $this->options['multiple'] = $multiple;
        return $this;
    }

    /**
     * Make the Attribute a single media element
     * 
     * This currently functions the same as ->media(multiple: false) but is more descriptive
     * 
     * @return Attribute
     */
    public function image(bool $ignoreOrganizationPrefix = false): Attribute
    {
        $this->media(multiple: false, ignoreOrganizationPrefix: $ignoreOrganizationPrefix);
        return $this;
    }

    /**
     * Make the Attribute a multiple media element
     * 
     * This currently functions the same as ->media() but is more descriptive
     * 
     * @return Attribute
     */
    public function images(bool $ignoreOrganizationPrefix = false): Attribute
    {
        $this->media(multiple: true, ignoreOrganizationPrefix: $ignoreOrganizationPrefix);
        return $this;
    }

    /**
     * Return the attribute values depending on type
     *
     * @return array
     */
    public function getValues(): array
    {
        if ($this->type == 'foreign') {
            return $this->valuesFromModel();
        } elseif ($this->type == 'pivot') {
            return $this->valuesFromModel();
        } elseif ($this->type == 'tree') {
            return $this->valuesFromModel();
        } else {
            return $this->values;
        }
    }

    /**
     * Return values from the model, this is used for foreign keys
     *
     * @return array
     */
    public function valuesFromModel(): array
    {
        $model = new $this->options['model'];
        $values = $this->type == 'foreign' ? [null] : [];

        // Apply scope and/or orderBy
        if ($this->options['orderBy']) {
            $model = $model->orderBy($this->options['orderBy']);
        }
        if ($this->options['scope']) {
            $model = $model->{$this->options['scope']}();
        }

        if ($this->options['index']) {
            // Get only index columns and the id
            $model = $model->get(array_merge($this->options['index'], [$this->options['id_column']]));
        } else {
            // Get all columns
            $model = $model->get();
        }

        foreach ($model as $value) {
            // Add the index columns or only the next 3 columns after the first (since first is usualy id)
            $values[$value->id] = implode('; ', $this->options['index'] ? $value->only($this->options['index']) : array_slice($value->toArray(), 1, 3));
        }

        return $values;
    }

    /**
     * Make the Attribute a foreign key, resulting in a select input element with all values from a foreign model
     *
     * @param [type] $model The model to get the values from
     * @param [type] $id_column The column to get the id from
     * @param [type] $scope The scope to apply
     * @param [type] $orderBy The column to order by
     * @param [type] $index The columns to show in the select input element, defaults to the next 3 columns after the first (since first is usualy id)
     * @return Attribute
     */
    public function foreign($model = null, $id_column = null, $scope = null, $orderBy = null, $index = null): Attribute
    {
        $this->type = 'foreign';
        $this->input = 'select';

        // Set model and id column based on column name, e.g. user_id will make the model App\Models\User referenced by id
        $this->options['model'] = $model ?: 'App\\Models\\' . ucfirst(explode('_', $this->name)[0]);
        $this->options['id_column'] = $id_column ?: explode('_', $this->name)[1] ?? 'id';
        $this->options['scope'] = $scope;
        $this->options['orderBy'] = $orderBy;

        // If index is set make sure it's an array
        $this->options['index'] = $index ? (is_array($index) ? $index : (explode(',', $index))) : null;

        return $this;
    }

    /**
     * Make the Attribute a pivot, resulting in a multiple select input element with all values from a pivot model. 
     * The belongsToMany relationship must be defined in the model with the same name.
     *
     * @param [type] $model The model to get the values from
     * @param [type] $id_column The column to get the id from
     * @param [type] $scope The scope to apply
     * @param [type] $orderBy The column to order by
     * @param [type] $index The columns to show in the editor interface, defaults to the next 3 columns after the first (since first is usualy id)
     * @return Attribute
     */
    public function pivot($model = null, $id_column = null, $scope = null, $orderBy = null, $index = null): Attribute
    {
        $this->type = 'pivot';
        $this->input = 'pivot';
        $this->wire = 'live';

        // Set model and id column based on column name, e.g. users will make the model App\Models\User
        $this->options['model'] = $model ?: 'App\\Models\\' . ucfirst(Str::singular($this->name));
        $this->options['id_column'] = $id_column ?: 'id';
        $this->options['scope'] = $scope;
        $this->options['orderBy'] = $orderBy;

        // If index is set make sure it's an array
        $this->options['index'] = $index ? (is_array($index) ? $index : (explode(',', $index))) : null;

        return $this;
    }

    public function tree(Module|null $module = null, $scope = null, $orderBy = null, $index = null): Attribute
    {
        $this->type = 'tree';
        $this->input = null;
        // $this->wire = 'live';

        // $this->options['model'] = $module->getModel()::class;
        // $this->options['scope'] = $scope;
        // $this->options['orderBy'] = $orderBy;

        // If index is set make sure it's an array
        // $this->options['index'] = $index ? (is_array($index) ? $index : (explode(',', $index))) : null;

        return $this;
    }

    /**
     * Make the Attribute a rich text editor input
     * 
     * This will enable the TinyMCE html editor for this attribute
     *
     * @param mixed ...$options Options to pass to TinyMCE config, will be merged with leap.tinymce.options config, can be arrays of options or variable-length argument, for example:
     *                          ->richtext(['skin' => 'oxide-dark', 'toolbar' => 'bold'])
     *                          ->richtext(skin: 'oxide', toolbar: 'bold')
     * @return Attribute
     */
    public function richtext(mixed ...$options): Attribute
    {
        // Merge options with leap.tinymce.options defaults
        $this->options = config('leap.tinymce.options');
        foreach ($options as $key => $option) {
            if (is_string($key)) {
                $this->options[$key] = $option;
            } elseif (is_array($option)) {
                $this->options = array_merge($this->options, $option);
            } else {
                dd($options);
            }
        }

        $this->input = 'tinymce';
        return $this;
    }

    /**
     * If the attribute should be shown in the index set the priority with ->index(priority)
     *
     * The default priority is 999. The Resource index() method will return all attributes with a priority sorted by priority.
     * 
     * @param integer $priority
     * @return Attribute
     */
    public function index(int $priority = 999): Attribute
    {
        $this->index = $priority;
        return $this;
    }

    /**
     * If the attribute should ONLY be shown in the index set the priority with ->indexOnly(priority)
     *
     * @param integer $priority
     * @return Attribute
     */
    public function indexOnly(int $priority = 999): Attribute
    {
        $this->index = $priority;
        $this->indexOnly = true;
        return $this;
    }

    /**
     * Set the label and optional index label for the attribute
     *
     * The label is a nice name for the attribute, it will be shown in the index and form. 
     * By default index label is the original attribute name passed thru Str::headline().
     * With this method you can change the label and index label.
     * The index label is only shown in the index and is usualy a shorter version of the label.
     * For example: Attribute::make('email')->label('Email address', 'Email')
     * 
     * @param string $label
     * @param string|null $labelIndex
     * @return Attribute
     */
    public function label(string|null $label = null, ?string $labelIndex = null): Attribute
    {
        $this->label = $label;
        if ($label) {
            $this->labelIndex = $labelIndex ?: $label;
        }
        return $this;
    }

    public function nullable(bool $nullable = true): Attribute
    {
        if ($nullable) {
            $this->validate[] = 'nullable';
        }
        return $this;
    }

    /**
     * Make the Attribute a number
     * 
     * The type will be set to number.
     *
     * @return Attribute
     */
    public function number(): Attribute
    {
        $this->type = 'number';
        return $this;
    }

    /**
     * Set the placeholder value
     *
     * @param string $placeholder
     * @return Attribute
     */
    public function placeholder(string $placeholder): Attribute
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    /**
     * Make the Attribute a password
     * 
     * The type will be set to password and a validation rules 'password' will be added.
     * Your models $casts array should contain 'password' => 'hashed'.
     *
     * @return Attribute
     */
    public function password(): Attribute
    {
        $this->type = 'password';
        $this->placeholder = '••••••••';
        return $this;
    }

    /**
     * Require the attribute to be confirmed
     * 
     * When set an extra input element will be shown in the editor to confirm the value.
     * Also a validation rule 'confirmed' will be added.
     * 
     * @param string $field The data name of the extra confirmation input
     * @return Attribute
     */
    public function confirmed(string $field = '{field}_confirmation'): Attribute
    {
        $this->confirmed = str_replace('{field}', $this->name, $field);
        $this->validate[] = 'confirmed:data.' . $this->confirmed;
        return $this;
    }

    /**
     * Create a copy of the attribute with the confirmed attribute as name
     * 
     * This will be used for the extra confirmation input in the editor
     *
     * @return Attribute
     */
    public function confirmedAttribute(): Attribute
    {
        $attribute = clone $this;
        $attribute->label = __('leap::resource.repeat') . ' ' . lcfirst($this->label);
        $attribute->name = $attribute->confirmed;
        $attribute->dataName = 'data.' . $attribute->name;
        $attribute->confirmed = null;
        return $attribute;
    }

    /**
     * Require the attribute to not be null
     *
     * @param boolean $required
     * @return Attribute
     */
    public function required(bool $required = true): Attribute
    {
        if ($required) {
            $this->validate[] = 'required';
        }
        return $this;
    }

    /**
     * Require the attribute to be accepted, value must be "yes", "on", 1, "1", true, or "true"
     *
     * @param boolean $accepted
     * @return Attribute
     */
    public function accepted(bool $accepted = true): Attribute
    {
        if ($accepted) {
            $this->validate[] = 'accepted';
        }
        return $this;
    }

    public function searchable(bool $searchable = true): Attribute
    {
        $this->searchable = $searchable;
        return $this;
    }

    public function filterable(bool $filterable = true): Attribute
    {
        $this->filterable = $filterable;
        return $this;
    }

    /**
     * If the target attribute is empty populate it with a slug of the current value
     * 
     * The slugified value will be shown as placeholder for the target input unless a value was already saved in the model.
     * An incrementing number will be appended to the slug to make it unique if the slug already exists.
     * Note that also setting a placeholder for the target input will have no effect.
     *
     * @param string $attribute attribute to use for slugging
     * @return Attribute
     */
    public function slugify(string $target): Attribute
    {
        $this->wire = 'live';
        $this->slugify = $target;
        return $this;
    }

    /**
     * Make the Attribute a textarea
     * 
     * @return Attribute
     */
    public function textarea($rows = 3): Attribute
    {
        $this->input = 'textarea';
        $this->rows = $rows;
        return $this;
    }

    /**
     * Set the attribute type
     *
     * @param string $type
     * @return Attribute
     */
    public function type(string $type): Attribute
    {
        $this->type = $type;
        return $this;
    }

    public function unique(null|string $table = null, null|string $column = null, bool $ignoreSelf = true, bool $ignoreSoftDeletes = false): Attribute
    {
        $this->validate[] = 'unique:' .
            ($table ?: '{table}') . ',' .
            ($column ?: $this->name) . ',' .
            ($ignoreSelf ? '{id}' : 'NULL') . ',id' .
            ($ignoreSoftDeletes ? ',deleted_at,NULL' : '');
        return $this;
    }

    public function validate(array|string|object $validate): Attribute
    {
        if (is_string($validate)) {
            $validate = explode('|', $validate);
        } elseif (is_object($validate)) {
            $validate = [$validate];
        }
        $this->validate = array_merge($this->validate, $validate);
        return $this;
    }

    public function inputAttributes(): string
    {
        $attributes = '';
        $attributes .= ' aria-label=' . $this->label . '';
        foreach (['type', 'step', 'rows', 'role'] as $attribute) {
            if ($this->$attribute) {
                $attributes .= ' ' . $attribute . '=' . $this->$attribute . '';
            }
        }
        return $attributes;
    }

    /**
     * Make the attribute sortable, this will allow dragging in the index and make it hidden in the editor by default
     *
     * @return Attribute
     */
    public function sortable(): Attribute
    {
        $this->type = 'sortable';
        $this->input = null;
        return $this;
    }

    /**
     * Set the values for the attribute
     * 
     * These are used for radio and select attributes.
     * If a list array is passed like ['Option 1', 'Option 2'] it will be converted to an associative array ['Option 1' => 'Option 1', 'Option 2' => 'Option 2']
     *
     * @param array $values
     * @return Attribute
     */
    public function values(array $values): Attribute
    {
        if (array_is_list($values)) {
            $this->values = array_combine($values, $values);
        } else {
            $this->values = $values;
        }
        return $this;
    }

    /**
     * Add or or more sections to the attribute
     *
     * @param Section ...$sections
     * @return Attribute
     */
    public function sections(Section ...$sections): Attribute
    {
        $this->type = 'sections';
        $this->input = 'sections';
        $this->sections = $sections;
        return $this;
    }

    /**
     * Define an attribute as an accessor, this will be a readonly attribute from the model and not the database
     *
     * @param string ...$columns The database columns required to be able to feed the accessor
     * @return Attribute
     */
    public function accessor(string ...$columns): Attribute
    {
        $this->isAccessor = true;
        $this->accessorColumns = $columns;
        $this->disabled = true;
        return $this;
    }

    /**
     * Add disabled attribute to the input making it readonly
     *
     * Effectively the same as readonly() but might have slight differences in future versions
     * 
     * @param bool $disabled
     * @return Attribute
     */
    public function disabled(bool $disabled = true): Attribute
    {
        $this->disabled = $disabled;
        return $this;
    }

    /**
     * Make the attribute readonly by adding the disabled attribute to the input
     *
     * Effectively the same as disabled() but might have slight differences in future versions
     * 
     * @param bool $readonly
     * @return Attribute
     */
    public function readonly(bool $readonly = true): Attribute
    {
        $this->disabled = $readonly;
        return $this;
    }

    /**
     * Make the attribute a json attribute allowing readonly access
     *
     * @return Attribute
     */
    public function json(): Attribute
    {
        $this->type = 'json';
        $this->input = 'json';
        return $this;
    }

    /**
     * Sets the default value for the attribute
     *
     * @param mixed $value
     * @return Attribute
     */
    public function default(mixed $value): Attribute
    {
        $this->default = $value;
        return $this;
    }

    /**
     * Make attribute hidden in index and editor but make it available for queries
     *
     * @param boolean $hidden
     * @return Attribute
     */
    public function hidden($hidden = true): Attribute
    {
        $this->hidden = $hidden;
        $this->accessor($this->name);
        return $this;
    }

    /**
     * Convert the attribute to an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
