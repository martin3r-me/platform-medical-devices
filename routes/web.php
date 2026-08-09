<?php

use Platform\MedicalDevices\Livewire\Dashboard;
use Platform\MedicalDevices\Livewire\Devices;
use Platform\MedicalDevices\Livewire\Inbox;

Route::get('/', Dashboard::class)->name('medical-devices.dashboard');

Route::get('/inbox', Inbox\Index::class)->name('medical-devices.inbox.index');

Route::get('/devices', Devices\Index::class)->name('medical-devices.devices.index');
