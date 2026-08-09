<?php

namespace Platform\MedicalDevices\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Platform\MedicalDevices\Models\MedicalDevice;

/**
 * Bearer-Geräte-Token für den GDT-Eingang. Löst das Gerät (und damit team_id) auf und
 * hängt es an den Request. Kein Web-Auth — das ist ein Maschinen-Endpunkt.
 */
class DeviceTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $device = MedicalDevice::resolveByToken($request->bearerToken());

        if (!$device) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $device->forceFill(['last_seen_at' => now()])->save();

        $request->attributes->set('medical_device', $device);

        return $next($request);
    }
}
