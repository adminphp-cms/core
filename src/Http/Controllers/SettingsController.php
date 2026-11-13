<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\Actions\SyncMediaUsages;
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

    public function update(Request $request, SyncMediaUsages $syncMediaUsages): JsonResponse
    {
        $settings = $this->settings();
        $values = $this->store->save($settings, $request->all());
        $syncMediaUsages->forSettings($settings);

        return response()->json([
            'data' => [
                'schema' => $settings->toSchema(),
                'values' => $values,
            ],
        ]);
    }

    protected function settings(): Settings
    {
        return $this->adminPhp->settingsForm() ?? abort(404);
    }
}
