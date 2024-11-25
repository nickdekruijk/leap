<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Support\Str;

class Attribute
{
    public static $_instance = null;

    public string $dataName;
    public string $name;
    public ?array $richtext = null;
    public ?int $index;
    public bool $indexOnly = false;
    public ?string $input = 'input';
    public ?int $rows = null;
    public string $label;
    public string $labelIndex;
    public string $placeholder = '';
    public bool $searchable = false;
    public ?string $slugify = null;
    public ?int $step = null;
    public string $type = 'text';
    public ?string $confirmed = null;
    public array $validate = [];
    public array $values = [];
    public string $wire = 'blur';

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

        self::$_instance->label = self::$_instance->labelIndex = Str::headline($name);

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
     * Make the Attribute a rich text editor input
     * 
     * This will enable the TinyMCE html editor for this attribute
     *
     * @return Attribute
     */
    public function richtext($editor = 'tinymce'): Attribute
    {
        $this->richtext = ['editor' => $editor];
        $this->input = $editor;
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
    public function label(string $label, ?string $labelIndex = null): Attribute
    {
        $this->label = $label;
        $this->labelIndex = $labelIndex ?: $label;
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
        $attribute->label = __('repeat') . ' ' . lcfirst($this->label);
        $attribute->name = $attribute->confirmed;
        $attribute->dataName = 'data.' . $attribute->name;
        $attribute->confirmed = null;
        return $attribute;
    }

    public function required(bool $required = true): Attribute
    {
        if ($required) {
            $this->validate[] = 'required';
        }
        return $this;
    }

    public function searchable(bool $searchable = true): Attribute
    {
        $this->searchable = $searchable;
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

    public function type(string $type): Attribute
    {
        $this->type = $type;
        return $this;
    }

    public function unique(string $table = null, string $column = null, bool $ignoreSelf = true, bool $ignoreSoftDeletes = false): Attribute
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
        foreach (['type', 'step', 'rows'] as $attribute) {
            if ($this->$attribute) {
                $attributes .= ' ' . $attribute . '=' . $this->$attribute . '';
            }
        }
        return $attributes;
    }

    public function values(array $values): Attribute
    {
        $this->values = $values;
        return $this;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
