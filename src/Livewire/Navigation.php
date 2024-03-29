<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\On;

class Navigation extends Component
{
    public $showOrganizations = false;
    public $currentUrl;

    #[On('update-navigation')]
    public function updateNavigation()
    {
    }

    public function render()
    {
        return view('leap::livewire.navigation');
    }

    public function mount()
    {
        $this->currentUrl = url()->current();
    }

    public function toggleOrganizations()
    {
        $this->showOrganizations = !$this->showOrganizations;
    }
}
