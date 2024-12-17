<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Module;

class Profile extends Module
{
    public $component = 'leap.profile';
    public $icon = 'fas-user-circle';
    public $slug = 'profile';
    public $priority = 1001;

    public $data;
    public $user;

    public $default_permissions = ['read', 'update'];

    public function mount()
    {
        $this->log('read');
        $this->user = Auth::user();
        $this->title = $this->user->name;
        $this->data['name'] = $this->user->name;
        $this->data['email'] = $this->user->email;
    }

    public function getTitle(): string
    {
        return Auth::user()->name;
    }

    public function rules()
    {
        return [
            'data.name' => 'required|min:3',
            'data.email' => 'required|email:rfc,spoof,strict,filter', // ,dns
            'data.password_current' => 'nullable|current_password:' . config('leap.guard') . '|required_with:data.password_new',
            'data.password_new' => ['nullable', 'different:data.password_current', Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
            'data.password_new_confirmation' => 'nullable|same:data.password_new|required_with:data.password_new',
        ];
    }

    public function validationAttributes()
    {
        $attributes = [];
        foreach ($this->rules() as $field => $rule) {
            $attributes[$field] = strtolower(__(explode('.', $field, 2)[1]));
        }
        return $attributes;
    }

    public function updated($field, $value)
    {
        $this->validateOnly($field);
    }

    public function submit()
    {
        Leap::validatePermission('update');

        // Run validation
        $validator = Validator::make(['data' => $this->data], $this->rules(), [], $this->validationAttributes());
        if ($validator->fails()) {
            // Show validation errors as toasts
            foreach ($validator->messages()->keys() as $fieldKey) {
                $this->dispatch('toast-error', $validator->messages()->first($fieldKey), $fieldKey)->to(Toasts::class);
            }
            // Show validation errors
            $validator->validate();
        } else {
            // Check if name is changed
            if ($this->user->name != $this->data['name']) {
                $this->user->name = $this->data['name'];
                $this->log('update', ['name' => $this->user->name]);
                $this->dispatch('toast', ucfirst($this->validationAttributes()['data.name']) . ' ' . __('updated'))->to(Toasts::class);
                // Update title and navigation to reflect name change
                $this->title = $this->user->name;
                $this->dispatch('update-navigation')->to(Navigation::class);
            }

            // Check if password is changed
            if (isset($this->data['password_new'])) {
                $this->log('update', 'new password');
                $this->user->password = bcrypt($this->data['password_new']);
                $this->dispatch('toast', __('password') . ' ' . __('updated'))->to(Toasts::class);
            }

            // Check if anything changed
            if ($this->user->isDirty()) {
                $this->user->save();
            } else {
                $this->dispatch('toast-alert', __('no-changes'))->to(Toasts::class);
            }
        }
    }

    public function render()
    {
        return view('leap::livewire.profile')->layout('leap::layouts.app');
    }
}
