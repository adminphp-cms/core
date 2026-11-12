<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Settings\Settings;
use Gmarineau\AdminPhp\Settings\SettingsStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The "Réglages" page: the settings form and its stored values.
 */
class SettingsController
{
    public function __construct(protected AdminPhp $adminPhp, protected SettingsStore $store) {}

    public function show(): JsonResponse
    {
        $settings = $this->settings();

        return response()->json([
            'data' => [
                'schema' => $settings->toSchema(),
                'values' => $this->store->formValues($settings),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $settings = $this->settings();

        return response()->json([
            'data' => [
                'schema' => $settings->toSchema(),
                'values' => $this->store->save($settings, $request->all()),
            ],
        ]);
    }

    protected function settings(): Settings
    {
        return $this->adminPhp->settingsForm() ?? abort(404);
    }
}
