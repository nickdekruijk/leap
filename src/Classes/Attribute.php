<?php

namespace NickDeKruijk\Leap\Classes;

use Illuminate\Support\Str;

class Attribute
{
    public static $_instance = null;

    public string $name;
    public ?int $index;
    public bool $indexOnly = false;
    public string $label;
    public string $labelIndex;
    public bool $searchable = false;
    public ?string $slugify;
    public string $type = 'text';
    public array $validate = [];
    public array $values = [];

    /**
     * Make a new Attribute instance and set default label based on name.
     *
     * @param string $name
     * @return Attribute
     */
    public static function make(string $name): Attribute
    {
        self::$_instance = new self;
        self::$_instance->name = $name;

        self::$_instance->label = self::$_instance->labelIndex = Str::headline($name);

        return self::$_instance;
    }

    /**
     * Make the Attribute an email
     * 
     * The type will be set to email and a validation rules 'email' will be added.
     *
     * @return Attribute
     */
    public function email(): Attribute
    {
        $this->type = 'email';
        $this->validate('email');
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
     * Make the Attribute a password
     * 
     * The type will be set to password and a validation rules 'password' will be added.
     *
     * @return Attribute
     */
    public function password(): Attribute
    {
        $this->type = 'password';
        $this->validate('password');
        return $this;
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

    public function slugify(string $slugify): Attribute
    {
        $this->slugify = $slugify;
        return $this;
    }

    public function type(string $type): Attribute
    {
        $this->type = $type;
        return $this;
    }

    public function unique(bool $unique = true): Attribute
    {
        if ($unique) {
            $this->validate[] = 'unique'; // :users,email,#id#
        }
        return $this;
    }

    public function validate(array|string $validate): Attribute
    {
        if (is_string($validate)) {
            $validate = explode('|', $validate);
        }
        $this->validate = array_merge($this->validate, $validate);
        return $this;
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