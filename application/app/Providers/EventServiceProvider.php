<?php

namespace App\Providers;

use App\Events\ClassifierValues\ClassifierValueDeleted;
use App\Events\ClassifierValues\ClassifierValueSaved;
use App\Events\Institutions\InstitutionDeleted;
use App\Events\Institutions\InstitutionSaved;
use App\Events\InstitutionUsers\InstitutionUserDeleted;
use App\Events\InstitutionUsers\InstitutionUserSaved;
use App\Listeners\ClassifierValues\DeleteClassifierValueListener;
use App\Listeners\ClassifierValues\SaveClassifierValueListener;
use App\Listeners\Institutions\DeleteInstitutionListener;
use App\Listeners\Institutions\SaveInstitutionListener;
use App\Listeners\InstitutionUsers\DeleteInstitutionUserListener;
use App\Listeners\InstitutionUsers\SaveInstitutionUserListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Models;
use App\Observers;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ClassifierValueSaved::class => [
            SaveClassifierValueListener::class,
        ],
        ClassifierValueDeleted::class => [
            DeleteClassifierValueListener::class,
        ],
        InstitutionSaved::class => [
            SaveInstitutionListener::class,
        ],
        InstitutionDeleted::class => [
            DeleteInstitutionListener::class,
        ],
        InstitutionUserSaved::class => [
            SaveInstitutionUserListener::class,
        ],
        InstitutionUserDeleted::class => [
            DeleteInstitutionUserListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Models\Vendor::observe(Observers\VendorObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
