<?php

namespace NickDeKruijk\Leap\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Attributes\On;
use NickDeKruijk\Leap\Module;

class Editor extends Module
{
    /**
     * The id of the row currently being edited, also toggles editor
     *
     * @var integer
     */
    public ?int $editing;
    public $parentModule;

    #[On('openEditor')]
    public function openEditor($id)
    {
        $this->editing = $id;
    }

    public function close()
    {
        $this->editing = null;
    }

    public function render()
    {
        return view('leap::livewire.editor');
    }
}
