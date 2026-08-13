<?php

namespace NickDeKruijk\Leap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * Per-request store for Leap's request-scoped state: the active module, the
 * current user's permission map, their role name and, on a preview request,
 * the record being previewed.
 *
 * Registered as a scoped binding (see ServiceProvider::register()), so it lives
 * for a single request / Livewire update and is flushed between them — unlike
 * Laravel's Context, it does not leak into queued jobs or logs.
 *
 * Backward compatibility: these values previously lived in Laravel's Context
 * under the hidden keys "leap.module", "leap.permissions" and
 * "leap.role.name". Each setter still mirrors to those keys during the 1.x
 * series so custom modules or middleware reading them keep working. The mirror
 * writes are @deprecated and will be removed in 2.0.
 */
class LeapContext
{
    /**
     * The active module's class name.
     */
    protected ?string $module = null;

    /**
     * Per-module permission map, keyed by module class name.
     *
     * @var array<string, array<string, mixed>>|null
     */
    protected ?array $permissions = null;

    /**
     * The current user's role name.
     */
    protected ?string $roleName = null;

    /**
     * The record this request is previewing, if it is a preview request at all.
     */
    protected ?Model $preview = null;

    /**
     * Whether that preview carries unsaved editor values.
     */
    protected bool $previewUnsaved = false;

    /**
     * Set the active module class name.
     */
    public function setModule(?string $module): static
    {
        $this->module = $module;

        // @deprecated 1.x backward-compat mirror, remove in 2.0
        Context::addHidden('leap.module', $module);

        return $this;
    }

    /**
     * Get the active module class name.
     */
    public function module(): ?string
    {
        return $this->module;
    }

    /**
     * Set the per-module permission map.
     *
     * @param  array<string, array<string, mixed>>|null  $permissions
     */
    public function setPermissions(?array $permissions): static
    {
        $this->permissions = $permissions;

        // @deprecated 1.x backward-compat mirror, remove in 2.0
        Context::addHidden('leap.permissions', $permissions);

        return $this;
    }

    /**
     * Get the full per-module permission map.
     *
     * @return array<string, array<string, mixed>>|null
     */
    public function permissions(): ?array
    {
        return $this->permissions;
    }

    /**
     * Get the permissions for a single module, defaulting to the active one.
     *
     * @return array<string, mixed>
     */
    public function permissionsFor(?string $module = null): array
    {
        return $this->permissions[$module ?? $this->module] ?? [];
    }

    /**
     * Set the current user's role name.
     */
    public function setRoleName(?string $name): static
    {
        $this->roleName = $name;

        // @deprecated 1.x backward-compat mirror, remove in 2.0
        Context::addHidden('leap.role.name', $name);

        return $this;
    }

    /**
     * Get the current user's role name.
     */
    public function roleName(): ?string
    {
        return $this->roleName;
    }

    /**
     * Mark this request as previewing the given record.
     *
     * Deliberately not mirrored to Laravel's Context the way the three above are.
     * Those mirrors exist because their keys used to live there; this one never
     * did, and Context is exactly the wrong place for it: it survives into queued
     * jobs and log entries, where an Eloquent model is both a serialisation hazard
     * and a way for one request's preview to colour something else entirely. This
     * has to end when the request does, which is what a scoped binding gives us.
     *
     * @param  bool  $unsaved  Whether unsaved editor values were applied to it
     */
    public function setPreview(?Model $record, bool $unsaved = false): static
    {
        $this->preview = $record;
        $this->previewUnsaved = $record ? $unsaved : false;

        return $this;
    }

    /**
     * The record being previewed, or null on any ordinary request.
     */
    public function preview(): ?Model
    {
        return $this->preview;
    }

    /**
     * Whether this request is a preview.
     *
     * Nothing in Leap queries differently because of this: a preview reaches its
     * record by id, so no scope had to be relaxed for it. It is here for the page
     * itself to say so -- a preview bar, a noindex tag -- and for nothing else.
     */
    public function isPreview(): bool
    {
        return $this->preview !== null;
    }

    /**
     * Whether the previewed record carries unsaved editor values.
     */
    public function previewIsUnsaved(): bool
    {
        return $this->previewUnsaved;
    }
}
