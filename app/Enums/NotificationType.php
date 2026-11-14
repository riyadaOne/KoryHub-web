<?php

namespace App\Enums;

/**
 * Types de notification (BACKEND-SCHEMA §7, TRD §19). Les types des modules suivants (nouvelle
 * version suivie, certification, signalement…) s'ajoutent avec leur module. Les types de
 * sécurité sont toujours envoyés ; les autres se règlent sur S-43.
 */
enum NotificationType: string
{
    case VersionPublished = 'version_published';
    case VersionRejected = 'version_rejected';
    case UpdateAvailable = 'update_available';
    case VersionDeprecated = 'version_deprecated';
    case NewVersionWatched = 'new_version_watched';
    case ModuleArchived = 'module_archived';
    case CertificationComplement = 'certification_complement';
    case CertificationGranted = 'certification_granted';
    case CertificationRefused = 'certification_refused';
    case CertificationRevoked = 'certification_revoked';
    case AdminCertificationRequested = 'admin_certification_requested';
    case VersionAtRisk = 'version_at_risk';
    case VersionRemoved = 'version_removed';
    case ReportReceived = 'report_received';
    case ReportClosed = 'report_closed';
    case AdminReportsMultiple = 'admin_reports_multiple';
    case AdminReportStale = 'admin_report_stale';
    case AdminIncident = 'admin_incident';
    case AdminWeeklyReport = 'admin_weekly_report';
    case AccountSuspended = 'account_suspended';
    case CliLogin = 'cli_login';
    case TokenCreated = 'token_created';
    case TokenRevoked = 'token_revoked';
    case TokenExpiring = 'token_expiring';
    case PasswordChanged = 'password_changed';

    public function isSecurity(): bool
    {
        return in_array($this, [self::CliLogin, self::TokenCreated, self::TokenRevoked, self::TokenExpiring, self::PasswordChanged, self::AccountSuspended], true);
    }

    /** Types réglables sur S-43, par groupe affiché. */
    public static function adjustable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => ! $type->isSecurity()));
    }

    /**
     * Groupe sur S-43 : mine (mes kory), installed (kory téléchargés, F-INT-06), watched (modules
     * suivis, F-RCH-09), reports (mes signalements, F-MOD-16), certification, admin, security (toujours envoyées).
     */
    public function group(): string
    {
        return match (true) {
            $this->isSecurity() => 'security',
            in_array($this, [self::UpdateAvailable, self::VersionDeprecated, self::VersionAtRisk, self::VersionRemoved], true) => 'installed',
            in_array($this, [self::NewVersionWatched, self::ModuleArchived], true) => 'watched',
            in_array($this, [self::AdminCertificationRequested, self::AdminReportsMultiple, self::AdminReportStale, self::AdminIncident, self::AdminWeeklyReport], true) => 'admin',
            in_array($this, [self::ReportReceived, self::ReportClosed], true) => 'reports',
            in_array($this, [self::CertificationComplement, self::CertificationGranted, self::CertificationRefused, self::CertificationRevoked], true) => 'certification',
            default => 'mine',
        };
    }

    /** Canal actif quand le compte n'a rien réglé : « mise à jour disponible » seulement dans la cloche, rapport du lundi seulement par e-mail. */
    public function enabledByDefault(string $channel): bool
    {
        return ! ($this === self::UpdateAvailable && $channel === 'mail')
            && ! ($this === self::AdminWeeklyReport && $channel === 'database');
    }

    /** Incident (W08) : toujours envoyé par e-mail ; seule la cloche se règle sur S-73. */
    public function alwaysByMail(): bool
    {
        return $this === self::AdminIncident;
    }

    public function label(): string
    {
        return __("notifications.types.{$this->value}");
    }
}
