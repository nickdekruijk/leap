<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Profile extends Component
{
    public $component = 'leap.profile';
    public $icon = 'fas-user-circle';
    public $priority = 1001;
    public $slug = 'profile';
    public $title;

    public $data;

    public function __construct($options = [])
    {
        // Use the proper authentication guard
        Auth::shouldUse(config('leap.guard'));

        // Overide default options
        foreach ($options as $option => $value) {
            $this->$option = $value;
        }

        // Set title to the current user name
        $this->title = Auth::user()->name;
    }

    public function mount()
    {
        $this->data['name'] = Auth::user()->name;
        $this->data['email'] = Auth::user()->email;
    }

    public function rules()
    {
        return [
            'data.name' => 'required|min:3',
            'data.email' => 'required|email:rfc,strict,dns,spoof,filter',
            'data.password_current' => 'nullable|current_password:' . config('leap.guard') . '|required_with:data.password_new',
            'data.password_new' => ['nullable', 'different:data.password_current', Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
            'data.password_new_confirmation' => 'nullable|same:data.password_new|required_with:data.password_new',
        ];
    }

    public function messages()
    {
        $messages = [];
        foreach (__('validation') as $rule => $message) {
            $messages[$rule] = $message;
        }
        return $messages;
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
        $this->validate();

        // Store changes
        if (Auth::user()->name != $this->data['name']) {
            Auth::user()->name = $this->data['name'];
            $this->dispatch('toast', ucfirst($this->validationAttributes()['data.name']) . ' ' . __('updated'))->to(Toasts::class);
            // Update title and navigation to reflect name change
            $this->title = Auth::user()->name;
            $this->dispatch('update-navigation')->to(Navigation::class);
        }
        if (isset($this->data['password_new'])) {
            Auth::user()->password = bcrypt($this->data['password_new']);
            $this->dispatch('toast', __('password') . ' ' . __('updated'))->to(Toasts::class);
        }
        if (Auth::user()->isDirty()) {
            Auth::user()->save();
        } else {
            $this->dispatch('toast-alert', __('no-changes'))->to(Toasts::class);
        }
    }

    public function render()
    {
        return view('leap::livewire.profile')->layout('leap::layouts.app');
    }
}
