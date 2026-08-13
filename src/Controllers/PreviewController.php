<?php

namespace NickDeKruijk\Leap\Controllers;

use Illuminate\Routing\Controller;
use NickDeKruijk\Leap\Classes\RecordDraft;
use NickDeKruijk\Leap\Contracts\Previewable;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Resource;
use NickDeKruijk\Leap\Traits\CanLog;
use Symfony\Component\HttpFoundation\Response;

/**
 * The frontend of one record, for the person editing it.
 *
 * The editor knows which record you have open; the frontend knows how a record
 * becomes a page. This route is the seam: it identifies the record, checks that
 * you may see it, sets the language, hands the record its unsaved values, and
 * lets the model render itself through the Previewable contract.
 *
 * It is deliberately a small hole. The record is fetched by id, so no scope had
 * to be relaxed anywhere: a page that is inactive still answers 404 on its own
 * URL while this preview is open, and a language the frontend does not publish
 * still has no addresses. The only thing that changes is that you, holding read
 * permission on the module the record belongs to, can look at it here.
 */
class PreviewController extends Controller
{
    use CanLog;

    public function __invoke(string $module, int $id, ?string $locale = null): Response
    {
        $resource = ModuleController::getModule($module);
        abort_unless($resource instanceof Resource, 404);

        // The module that owns the record is the whole gate. 404 rather than 403,
        // the same answer Module::boot() gives, so a preview URL gives away no more
        // about what exists than the module screen does.
        Leap::context()->setModule($resource::class);
        Leap::validatePermission('read', 404);

        // Only the application knows how one of its records becomes a page, and the
        // module is where it says so. One that does not has no preview.
        abort_unless($resource instanceof Previewable, 404);

        // No active()/published() scope: seeing what is not live yet is the point.
        // Soft-deleted rows stay out of reach, find() already excludes them.
        $record = $resource->getModel()->newQuery()->find($id);
        abort_unless($record !== null, 404);

        if ($locale !== null) {
            // Configured, not published: a language being written is exactly the one
            // you cannot reach on the frontend and most need to look at here.
            abort_unless(array_key_exists($locale, config('leap.locales') ?: []), 404);
            app()->setLocale($locale);
        }

        $unsaved = RecordDraft::applyStash($record, $resource, $module, $id);

        Leap::context()->setPreview($record, $unsaved);

        $this->log('preview', ['id' => $id, 'locale' => $locale, 'unsaved' => $unsaved]);

        $response = $resource->previewResponse($record);

        // Set here rather than left to the frontend's <head>: a preview URL that gets
        // shared or crawled must stay out of an index whatever the template does with
        // its meta tags, and must not sit in a shared cache either.
        return ($response instanceof Response ? $response : response($response))
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
    }
}
