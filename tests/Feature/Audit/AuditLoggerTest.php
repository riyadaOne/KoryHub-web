<?php

use App\Enums\ActorType;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Journal d'audit en ajout seul (TRD §21, T-29, F-ADM-04, F-ADM-05).
 */

test("un événement enregistre l'auteur, l'objet et les valeurs avant et après", function () {
    $actor = User::factory()->create();
    $subject = User::factory()->create();

    $event = app(AuditLogger::class)->record('account.locale_changed', $subject, ['locale' => ['en', 'fr']], actor: $actor);

    expect($event->actor_type)->toBe(ActorType::User)
        ->and($event->actor_id)->toBe($actor->id)
        ->and($event->subject_id)->toBe($subject->id)
        ->and($event->changes)->toHaveCount(1)
        ->and($event->changes->first()->only(['field', 'before', 'after']))
        ->toBe(['field' => 'locale', 'before' => 'en', 'after' => 'fr']);
});

test('sans compte connecté, ou pour un automatisme, l\'auteur est Système', function () {
    $subject = User::factory()->create();

    $this->actingAs(User::factory()->create());
    $event = app(AuditLogger::class)->record('token.expired', $subject, bySystem: true);

    expect($event->actor_type)->toBe(ActorType::System)->and($event->actor_id)->toBeNull();
});

test('le compte connecté est retenu comme auteur par défaut', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $event = app(AuditLogger::class)->record('module.archived', User::factory()->create());

    expect($event->actor_id)->toBe($actor->id);
});

test('les données personnelles sont notées comme modifiées, sans leur valeur', function () {
    $event = app(AuditLogger::class)->record('account.email_changed', User::factory()->create(), [
        'email' => ['ancien@exemple.td', 'nouveau@exemple.td'],
    ]);

    expect($event->changes->first()->only(['field', 'before', 'after']))
        ->toBe(['field' => 'email', 'before' => null, 'after' => null]);
});

test('MySQL refuse toute modification du journal', function () {
    $event = app(AuditLogger::class)->record('module.archived', User::factory()->create(), ['status' => ['active', 'archived']]);

    expect(fn () => DB::table('audit_events')->where('id', $event->id)->update(['action' => 'falsifié']))
        ->toThrow(QueryException::class, 'append-only');

    expect(fn () => DB::table('audit_event_changes')->where('audit_event_id', $event->id)->update(['after' => 'falsifié']))
        ->toThrow(QueryException::class, 'append-only');
});

test('MySQL refuse toute suppression dans le journal', function () {
    $event = app(AuditLogger::class)->record('module.archived', User::factory()->create(), ['status' => ['active', 'archived']]);

    expect(fn () => DB::table('audit_event_changes')->where('audit_event_id', $event->id)->delete())
        ->toThrow(QueryException::class, 'append-only');

    expect(fn () => DB::table('audit_events')->where('id', $event->id)->delete())
        ->toThrow(QueryException::class, 'append-only');
});

test('le model refuse aussi la modification et la suppression', function () {
    $event = app(AuditLogger::class)->record('module.archived', User::factory()->create());

    expect(fn () => $event->update(['action' => 'falsifié']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class)
        ->and(AuditEvent::find($event->id)->action)->toBe('module.archived');
});

test('les changements d\'un model enregistré sont lus avec leur valeur précédente', function () {
    $user = User::factory()->create(['name' => 'Moussa']);
    $user->update(['name' => 'Moussa Mahamat']);

    expect(app(AuditLogger::class)->changesOf($user))->toBe(['name' => ['Moussa', 'Moussa Mahamat']]);
});
