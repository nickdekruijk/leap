<?php

namespace NickDeKruijk\Leap\Contracts;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

/**
 * A module whose records can be previewed on the frontend from the editor.
 *
 * The panel knows which record is being edited; only the application knows how that
 * record becomes a page. This is where it says so. A module that implements this gets a
 * preview button; one that does not, does not.
 *
 * It lives on the module rather than the model because the module is where a project
 * already describes this screen — its attributes, its title, its icon — and because the
 * preview route is addressed by module slug, so this is the thing that was asked for.
 * The model stays free of it.
 *
 * By the time this is called the controller has checked the read permission of this very
 * module, applied the requested locale, loaded the record without any active/published
 * scope, and written any unsaved editor values onto it. So it only has to render:
 *
 *     public function previewResponse(Model $record): View
 *     {
 *         return view('page', ['page' => $record]);
 *     }
 *
 * Render it the same way the live route does rather than through a second view, or the
 * preview slowly stops resembling the page it is previewing. Nothing here needs to think
 * about robots or caching: the controller puts noindex on the response.
 */
interface Previewable
{
    /**
     * One of this module's records, as its frontend page.
     */
    public function previewResponse(Model $record): View|Response;
}
