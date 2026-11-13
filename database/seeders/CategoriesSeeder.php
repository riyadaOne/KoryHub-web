<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Catégories officielles de départ (F-RCH-11, plan W03, validées le 2026-11-13) : données de
 * base, lancées aussi en production ; gérées ensuite par l'admin (W08). Relançable sans doublon.
 */
class CategoriesSeeder extends Seeder
{
    /** @var array<string, array{en: string, fr: string, ar: string, pt: string}> */
    private const CATEGORIES = [
        'payments' => ['en' => 'Payments', 'fr' => 'Paiements', 'ar' => 'المدفوعات', 'pt' => 'Pagamentos'],
        'messaging' => ['en' => 'Messaging (SMS, USSD)', 'fr' => 'Messagerie (SMS, USSD)', 'ar' => 'المراسلة (SMS، USSD)', 'pt' => 'Mensagens (SMS, USSD)'],
        'offline-sync' => ['en' => 'Offline and sync', 'fr' => 'Hors ligne et synchronisation', 'ar' => 'العمل دون اتصال والمزامنة', 'pt' => 'Offline e sincronização'],
        'auth-security' => ['en' => 'Authentication and security', 'fr' => 'Authentification et sécurité', 'ar' => 'المصادقة والأمان', 'pt' => 'Autenticação e segurança'],
        'documents-pdf' => ['en' => 'Documents and PDF', 'fr' => 'Documents et PDF', 'ar' => 'المستندات وPDF', 'pt' => 'Documentos e PDF'],
        'data-api' => ['en' => 'Data and API', 'fr' => 'Données et API', 'ar' => 'البيانات وواجهات API', 'pt' => 'Dados e API'],
        'interface' => ['en' => 'Interface', 'fr' => 'Interface', 'ar' => 'الواجهة', 'pt' => 'Interface'],
        'tools' => ['en' => 'Tools', 'fr' => 'Outils', 'ar' => 'الأدوات', 'pt' => 'Ferramentas'],
    ];

    public function run(): void
    {
        $position = 0;

        foreach (self::CATEGORIES as $slug => $names) {
            $category = Category::withTrashed()->firstOrCreate(['slug' => $slug], ['position' => $position]);

            foreach ($names as $locale => $name) {
                $category->translations()->updateOrCreate(['locale' => $locale], ['name' => $name]);
            }

            $position++;
        }
    }
}
