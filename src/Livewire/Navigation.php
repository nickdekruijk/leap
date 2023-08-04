<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\On;

class Navigation extends Component
{
    public $currentModule;

    public function __construct()
    {
        // Use the proper authentication guard
        Auth::shouldUse(config('leap.guard'));
    }

    #[On('update-navigation')]
    public function updateNavigation()
    {
    }

    public function mount($currentModule)
    {
        $this->currentModule = $currentModule ? $currentModule->slug : null;
    }

    public function render()
    {
        return view('leap::livewire.navigation');
    }
}
