<?php

// Signalements et modération (W07) : feuille « Signaler », S-48, administration.
return [
    'more' => 'Mais ações',
    'report' => 'Denunciar',
    'review_of' => 'Avaliação de :handle sobre :module',
    'target_gone' => 'Item eliminado',
    'review_hidden_mine' => 'A sua avaliação foi ocultada pela moderação. Já não é mostrada nem contada.',
    'status' => [
        'open' => 'Aberta',
        'in_review' => 'Em análise',
        'closed_no_action' => 'Fechada sem ação',
        'closed_with_action' => 'Fechada com ação',
    ],
    'reason' => [
        'malware' => 'Software malicioso',
        'illegal_content' => 'Conteúdo ilegal',
        'copyright' => 'Direitos de autor',
        'harassment' => 'Assédio',
    ],
    'reason_help' => [
        'malware' => 'Código que rouba, destrói ou espia.',
        'illegal_content' => 'Conteúdo proibido por lei.',
        'copyright' => 'Código copiado sem respeitar a sua licença.',
        'harassment' => 'Conteúdo dirigido a uma pessoa.',
    ],
    'target' => [
        'module' => 'Módulo',
        'version' => 'Versão',
        'user' => 'Conta',
        'review' => 'Avaliação',
    ],
    'resolution' => [
        'security_warning' => 'Aviso de segurança',
        'legal_removal' => 'Remoção legal',
        'review_hidden' => 'Avaliação ocultada',
        'account_suspended' => 'Conta suspensa',
        'account_deleted' => 'Conta eliminada',
    ],
    'sheet' => [
        'reason' => 'Motivo',
        'details' => 'Explicação',
        'optional' => 'opcional',
        'anonymous' => 'A equipa de moderação analisa cada denúncia. A pessoa denunciada nunca vê quem a denunciou.',
        'send' => 'Enviar denúncia',
        'sent_title' => 'Denúncia enviada',
        'sent' => 'Obrigado. A equipa de moderação vai analisá-la.',
        'sent_text' => 'Será informado da decisão. Acompanhe as suas denúncias em',
        'verify' => 'Verificar o meu endereço de e-mail',
    ],
    'errors' => [
        'target_not_found' => 'Este item não pode ser denunciado.',
        'own_target' => 'Não pode denunciar algo que lhe pertence.',
        'email_unverified' => 'Verifique o seu endereço de e-mail para denunciar.',
        'duplicate' => 'Já tem uma denúncia aberta sobre este item.',
    ],
    'mine' => [
        'title' => 'As minhas denúncias',
        'subtitle' => 'As suas denúncias e o resultado.',
        'empty' => 'Nenhuma denúncia',
        'target' => 'Item',
        'reason' => 'Motivo',
        'status' => 'Estado',
        'sent' => 'Enviada',
        'no_action' => 'Sem ação',
        'with_action' => 'Foi tomada uma medida',
    ],
];
