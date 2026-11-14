<?php

// Signalements et modération (W07) : feuille « Signaler », S-48, administration.
return [
    'more' => 'Plus d\'actions',
    'report' => 'Signaler',
    'review_of' => 'Avis de :handle sur :module',
    'target_gone' => 'Objet supprimé',
    'review_hidden_mine' => 'Votre avis a été masqué par la modération. Il n\'est plus affiché ni compté.',
    'status' => [
        'open' => 'Ouvert',
        'in_review' => 'En examen',
        'closed_no_action' => 'Clos sans suite',
        'closed_with_action' => 'Clos avec action',
    ],
    'reason' => [
        'malware' => 'Logiciel malveillant',
        'illegal_content' => 'Contenu illégal',
        'copyright' => 'Droit d\'auteur',
        'harassment' => 'Harcèlement',
    ],
    'reason_help' => [
        'malware' => 'Code qui vole, détruit ou espionne.',
        'illegal_content' => 'Contenu interdit par la loi.',
        'copyright' => 'Code copié sans respecter sa licence.',
        'harassment' => 'Propos qui visent une personne.',
    ],
    'target' => [
        'module' => 'Module',
        'version' => 'Version',
        'user' => 'Compte',
        'review' => 'Avis',
    ],
    'resolution' => [
        'security_warning' => 'Avertissement de sécurité',
        'legal_removal' => 'Retrait légal',
        'review_hidden' => 'Avis masqué',
        'account_suspended' => 'Compte suspendu',
        'account_deleted' => 'Compte supprimé',
    ],
    'sheet' => [
        'reason' => 'Motif',
        'details' => 'Explication',
        'optional' => 'facultatif',
        'anonymous' => 'L\'équipe de modération examine chaque signalement. La personne signalée ne voit pas qui l\'a signalée.',
        'send' => 'Envoyer le signalement',
        'sent_title' => 'Signalement envoyé',
        'sent' => 'Merci. L\'équipe de modération va l\'examiner.',
        'sent_text' => 'Vous serez prévenu de la décision. Suivez vos signalements dans',
        'verify' => 'Vérifier mon adresse e-mail',
    ],
    'errors' => [
        'target_not_found' => 'Cet objet ne peut pas être signalé.',
        'own_target' => 'Vous ne pouvez pas signaler ce qui vous appartient.',
        'email_unverified' => 'Vérifiez votre adresse e-mail pour signaler.',
        'duplicate' => 'Vous avez déjà un signalement ouvert sur cet objet.',
    ],
    'mine' => [
        'title' => 'Mes signalements',
        'subtitle' => 'Vos signalements et leur résultat.',
        'empty' => 'Aucun signalement',
        'target' => 'Objet',
        'reason' => 'Motif',
        'status' => 'Statut',
        'sent' => 'Envoyé',
        'no_action' => 'Sans suite',
        'with_action' => 'Une mesure a été prise',
    ],
];
