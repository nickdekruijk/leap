<?php

namespace NickDeKruijk\Leap\Traits;

use Illuminate\Contracts\Validation\Validator;
use NickDeKruijk\Leap\Livewire\Toasts;

trait ToastsValidationErrors
{
    /**
     * Show every message of a failed validator as an error toast, keyed by field
     * so the toast can highlight the input it belongs to.
     */
    protected function toastValidationErrors(Validator $validator): void
    {
        foreach ($validator->messages()->keys() as $fieldKey) {
            $this->dispatch('toast-error', $validator->messages()->first($fieldKey), $fieldKey)->to(Toasts::class);
        }
    }
}
