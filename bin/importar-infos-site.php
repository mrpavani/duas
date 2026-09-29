<?php
// ==========================================================================
// DUÁS - IMPORTAÇÃO DE PRODUTOS DO DOCUMENTO "Infos site.docx"
// Zera os dados fictícios e cadastra a coleção oficial.
// ==========================================================================

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

echo "=== DUÁS: INICIANDO LIMPEZA E IMPORTAÇÃO ===\n\n";

// 1. Limpeza de dados fictícios
echo "1. Limpando dados fictícios antigos...\n";
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

$tablesToTruncate = [
    'order_events',
    'order_items',
    'orders',
    'customers',
    'cart_items',
    'stock_notifications',
    'product_images',
    'product_sizes',
    'products',
    'blog_posts',
    'contact_messages',
    'newsletter_subscribers',
];

foreach ($tablesToTruncate as $tbl) {
    $pdo->exec("TRUNCATE TABLE `$tbl`");
    echo "   - Tabela `$tbl` zerada.\n";
}

// Limpar promoções fictícias de teste
$pdo->exec("DELETE FROM `promotions` WHERE `code` IN ('DUAS20', 'VERAO26', 'BF2025', 'PRIMEIRA')");
$pdo->exec("DELETE FROM `free_shipping_rules` WHERE `label` = 'Campanha de aniversário'");

$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
echo "[OK] Banco de dados limpo com sucesso.\n\n";

// 2. Catálogo a ser inserido
$catalog = [
    [
        'name' => 'Conjunto 10pm',
        'slug' => 'conjunto-10pm',
        'category' => 'Conjuntos',
        'price' => 1399.90,
        'image' => 'uploads/products/image1.JPG',
        'sizes' => ['PP' => 2, 'P' => 1],
        'description' => 'O Conjunto 10pm é confeccionado em tecido vinil, trazendo um acabamento brilhante e um visual moderno. Composto por jaqueta cropped e short de cintura alta, o conjunto valoriza a silhueta com uma modelagem sofisticada e cheia de personalidade. A jaqueta possui gola alta, mangas longas, ombros estruturados e fechamento frontal por zíper, enquanto o short oferece um caimento confortável e elegante. Perfeito para festas, eventos e produções fashionistas, é uma peça versátil para quem busca um look marcante e contemporâneo.',
        'composition' => 'Tecido vinil com acabamento brilhante.',
        'care_instructions' => 'Lavar à mão em água fria. Não torcer. Secar à sombra.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Azul Claro.',
            'rows' => [
                'PP' => ['busto' => '82 cm', 'cintura' => '70 cm', 'quadril' => '90 cm', 'comprimento' => ''],
                'P'  => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '94 cm', 'comprimento' => ''],
                'M'  => ['busto' => '90 cm', 'cintura' => '79 cm', 'quadril' => '100 cm', 'comprimento' => ''],
                'G'  => ['busto' => '94 cm', 'cintura' => '83 cm', 'quadril' => '106 cm', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Vestido Martini',
        'slug' => 'vestido-martini',
        'category' => 'Vestidos',
        'price' => 649.90,
        'image' => 'uploads/products/image2.JPG',
        'sizes' => ['PP' => 1, 'P' => 1, 'M' => 2, 'G' => 1],
        'description' => 'O Vestido Martini é curto, em paetê grande com efeito perolado num tom off white que reflete a luz a cada movimento. O contraste é o que encanta: gola alta e frente comportada dão lugar a costas totalmente nuas, num decote profundo que revela na medida certa. Peça-desejo para as ocasiões que pedem protagonismo — réveillon, aniversários, festas e noites em que você quer ser a atração. Deixe o vestido falar por si: finalize com sandália de tira fina e brincos discretos para o brilho do paetê ser a estrela.',
        'composition' => 'Paetê perolado de alto brilho com forro confortável.',
        'care_instructions' => 'Limpeza especializada a seco ou lavagem manual suave.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Bege / Off White.',
            'rows' => [
                'PP' => ['busto' => '82 cm', 'cintura' => '70 cm', 'quadril' => '90 cm', 'comprimento' => '36'],
                'P'  => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '94 cm', 'comprimento' => '38'],
                'M'  => ['busto' => '90 cm', 'cintura' => '79 cm', 'quadril' => '100 cm', 'comprimento' => '40'],
                'G'  => ['busto' => '94 cm', 'cintura' => '83 cm', 'quadril' => '106 cm', 'comprimento' => '42'],
            ]
        ]
    ],
    [
        'name' => 'Vestido Champagne',
        'slug' => 'vestido-champagne',
        'category' => 'Vestidos',
        'price' => 1349.90,
        'image' => 'uploads/products/image3.JPG',
        'sizes' => ['P' => 3],
        'description' => 'O Vestido Champagne é longo, em malha lurex metalizada num tom marrom profundo que reflete a luz com sofisticação. O decote cowl drapeado cai fluido sobre o colo, as alças finas frente-única deixam as costas em evidência, e a fenda na barra libera o movimento uma peça que veste como líquido. Feito para as noites em que você quer elegância com atitude: jantares, festas e eventos que pedem um look de impacto sem esforço. Finalize com sandália de tira fina e acessórios minimalistas para o brilho do tecido ser protagonista.',
        'composition' => 'Malha lurex metalizada com caimento líquido.',
        'care_instructions' => 'Para preservar a beleza da peça, vista e guarde conforme recebida. Manter amarrado original sem desfazê-lo. Evitar contato com anéis e superfícies ásperas.',
        'measurements' => [
            'note' => 'A modelo veste P. Cor: Bege / Marrom Metalizado.',
            'rows' => [
                'PP' => ['busto' => '82 cm', 'cintura' => '70 cm', 'quadril' => '90 cm', 'comprimento' => '36'],
                'P'  => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '94 cm', 'comprimento' => '38'],
                'M'  => ['busto' => '90 cm', 'cintura' => '79 cm', 'quadril' => '100 cm', 'comprimento' => '40'],
                'G'  => ['busto' => '94 cm', 'cintura' => '83 cm', 'quadril' => '106 cm', 'comprimento' => '42'],
            ]
        ]
    ],
    [
        'name' => 'Vestido Merlot',
        'slug' => 'vestido-merlot',
        'category' => 'Vestidos',
        'price' => 749.90,
        'image' => 'uploads/products/image4.JPG',
        'sizes' => ['PP' => 3, 'P' => 1, 'M' => 2],
        'description' => 'Modelo curto, tomara que caia, confeccionado em crepe musseline leve e com caimento impecável. Possui camadas de babados que criam movimento e volume na peça, deixando o visual moderno e super elegante.',
        'composition' => 'Crepe musseline fluido e leve.',
        'care_instructions' => 'Lavar à mão ou a seco profissionalmente. Secar na horizontal à sombra.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Vermelho.',
            'rows' => [
                'PP' => ['busto' => '82 cm', 'cintura' => '70 cm', 'quadril' => '90 cm', 'comprimento' => ''],
                'P'  => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '94 cm', 'comprimento' => ''],
                'M'  => ['busto' => '90 cm', 'cintura' => '79 cm', 'quadril' => '100 cm', 'comprimento' => ''],
                'G'  => ['busto' => '94 cm', 'cintura' => '83 cm', 'quadril' => '106 cm', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Vestido Amour - Vermelho',
        'slug' => 'vestido-amour-vermelho',
        'category' => 'Vestidos',
        'price' => 399.90,
        'image' => 'uploads/products/image5.JPG',
        'sizes' => ['PP' => 1, 'P' => 2],
        'description' => 'Vestido curto tomara que caia com modelagem moderna e elegante na cor vermelho. Confeccionado em tecido estruturado, possui caimento impecável que valoriza a silhueta com sofisticação. O design minimalista e ajustado ao corpo traz um visual versátil e atemporal, perfeito para composições elegantes em festas, eventos e ocasiões especiais.',
        'composition' => 'Tecido estruturado nobre encorpado.',
        'care_instructions' => 'Lavar à mão em temperatura ambiente. Não alvejar.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Vermelho.',
            'rows' => [
                'PP' => ['busto' => '82 cm', 'cintura' => '70 cm', 'quadril' => '90 cm', 'comprimento' => ''],
                'P'  => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '94 cm', 'comprimento' => ''],
                'M'  => ['busto' => '90 cm', 'cintura' => '79 cm', 'quadril' => '100 cm', 'comprimento' => ''],
                'G'  => ['busto' => '94 cm', 'cintura' => '83 cm', 'quadril' => '106 cm', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Vestido Amour - Preto',
        'slug' => 'vestido-amour-preto',
        'category' => 'Vestidos',
        'price' => 399.90,
        'image' => 'uploads/products/image6.JPG',
        'sizes' => ['PP' => 2, 'P' => 1, 'M' => 2],
        'description' => 'Vestido curto tomara que caia com modelagem moderna e elegante na cor preto. Confeccionado em tecido estruturado, possui caimento impecável que valoriza a silhueta com sofisticação. O design minimalista e ajustado ao corpo traz um visual versátil e atemporal, perfeito para composições elegantes em festas, eventos e ocasiões especiais.',
        'composition' => 'Tecido estruturado nobre encorpado.',
        'care_instructions' => 'Lavar à mão em temperatura ambiente. Não alvejar.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Preto.',
            'rows' => [
                'PP' => ['busto' => '82 cm', 'cintura' => '70 cm', 'quadril' => '90 cm', 'comprimento' => ''],
                'P'  => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '94 cm', 'comprimento' => ''],
                'M'  => ['busto' => '90 cm', 'cintura' => '79 cm', 'quadril' => '100 cm', 'comprimento' => ''],
                'G'  => ['busto' => '94 cm', 'cintura' => '83 cm', 'quadril' => '106 cm', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Conjunto Moonlight',
        'slug' => 'conjunto-moonlight',
        'category' => 'Conjuntos',
        'price' => 749.90,
        'image' => 'uploads/products/image7.JPG',
        'sizes' => ['PP' => 2, 'G' => 1],
        'description' => 'Conjunto em alfaiataria, moderno e sofisticado, ideal para produções fashionistas com elegância minimalista. O cropped de gola alta possui modelagem estruturada e mangas curtas, trazendo um toque contemporâneo e refinado. O comprimento mais curto valoriza a silhueta e cria um contraste equilibrado com a parte inferior. A mini saia em alfaiataria apresenta corte preciso e caimento impecável, com detalhe de faixa alongada lateral que adiciona movimento e personalidade ao look, elevando a proposta clássica com um design atual. Confeccionado em tecido de alfaiataria encorpado, o conjunto une estrutura, conforto e acabamento elegante.',
        'composition' => 'Alfaiataria encorpada premium com forro macio.',
        'care_instructions' => 'Lavagem delicada à mão ou a seco profissional.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Branco.',
            'rows' => [
                'PP' => ['busto' => '82 cm', 'cintura' => '70 cm', 'quadril' => '90 cm', 'comprimento' => ''],
                'P'  => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '94 cm', 'comprimento' => ''],
                'M'  => ['busto' => '90 cm', 'cintura' => '79 cm', 'quadril' => '100 cm', 'comprimento' => ''],
                'G'  => ['busto' => '94 cm', 'cintura' => '83 cm', 'quadril' => '106 cm', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Vestido Noir',
        'slug' => 'vestido-noir',
        'category' => 'Vestidos',
        'price' => 649.90,
        'image' => 'uploads/products/image8.JPG',
        'sizes' => ['PP' => 1, 'M' => 1],
        'description' => 'Confeccionado em tecido crepe musseline fluido de alta qualidade, o Noir combina sensualidade sofisticada com elegância atemporal. O bustier estruturado com cut out frontal cria um recorte estratégico que valoriza o decote com ousadia calculada, enquanto as alças finas adicionam delicadeza e leveza ao visual. A modelagem sereia abraça cada curva do corpo com precisão, culminando em uma cauda generosa que transforma qualquer passagem em um momento cinematográfico. Do tapete vermelho à formatura, do baile de gala ao casamento — o Noir foi criado para ocasiões que merecem ser eternas.',
        'composition' => 'Crepe musseline fluido de alta gramatura.',
        'care_instructions' => 'Apenas lavagem especializada a seco.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Preta.',
            'rows' => [
                'PP' => ['busto' => '82 cm', 'cintura' => '70 cm', 'quadril' => '90 cm', 'comprimento' => ''],
                'P'  => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '94 cm', 'comprimento' => ''],
                'M'  => ['busto' => '90 cm', 'cintura' => '79 cm', 'quadril' => '100 cm', 'comprimento' => ''],
                'G'  => ['busto' => '94 cm', 'cintura' => '83 cm', 'quadril' => '106 cm', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Calça Sunrise',
        'slug' => 'calca-sunrise',
        'category' => 'Calças',
        'price' => 224.90,
        'image' => 'uploads/products/image9.JPG',
        'sizes' => ['P' => 2, 'M' => 2],
        'description' => 'Calça Sunrise em tom off white com modelagem sofisticada e corte impecável. Desenvolvida para valorizar a silhueta com leveza e movimento, proporcionando conforto e elegância tanto para o cotidiano quanto para ocasiões especiais.',
        'composition' => 'Tecido creponado encorpado com caimento pesado.',
        'care_instructions' => 'Lavar à mão ou em ciclo suave. Secar à sombra.',
        'measurements' => [
            'note' => 'A modelo veste P. Cor: Off White.',
            'rows' => [
                'P' => ['busto' => '', 'cintura' => '74 cm', 'quadril' => '94 cm', 'comprimento' => ''],
                'M' => ['busto' => '', 'cintura' => '79 cm', 'quadril' => '100 cm', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Top Sunset - Amarelo Claro',
        'slug' => 'top-sunset-amarelo-claro',
        'category' => 'Tops',
        'price' => 49.90,
        'image' => 'uploads/products/image10.JPG',
        'sizes' => ['P' => 2],
        'description' => 'Top Sunset na cor amarelo claro com modelagem minimalista e caimento confortável. Uma peça charmosa e fresca para combinações solares e descontraídas.',
        'composition' => 'Algodão com elastano de toque suave.',
        'care_instructions' => 'Lavar com cores similares em água fria.',
        'measurements' => [
            'note' => 'A modelo veste P. Cor: Amarelo Claro.',
            'rows' => [
                'P' => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Top Sunset - Marrom',
        'slug' => 'top-sunset-marrom',
        'category' => 'Tops',
        'price' => 49.90,
        'image' => 'uploads/products/image11.jpg',
        'sizes' => ['M' => 1],
        'description' => 'Top Sunset na cor marrom com corte moderno e ajuste ao corpo. Uma peça básica sofisticada que transita perfeitamente do dia para a noite.',
        'composition' => 'Algodão com elastano de toque macio.',
        'care_instructions' => 'Lavar com cores similares em água fria.',
        'measurements' => [
            'note' => 'A modelo veste M. Cor: Marrom.',
            'rows' => [
                'M' => ['busto' => '90 cm', 'cintura' => '79 cm', 'quadril' => '', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Regata Lumi - Preta',
        'slug' => 'regata-lumi-preta',
        'category' => 'Regatas',
        'price' => 149.90,
        'image' => 'uploads/products/image12.JPG',
        'sizes' => ['PP' => 1, 'P' => 1],
        'description' => 'Regata Lumi em preto essencial com decote sutil e acabamento refinado. Perfeita para sobreposições contemporâneas com blazers ou saias fluidas.',
        'composition' => 'Toque de seda com caimento fluido.',
        'care_instructions' => 'Lavar à mão com sabão neutro.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Preta.',
            'rows' => [
                'PP' => ['busto' => '82 cm', 'cintura' => '70 cm', 'quadril' => '', 'comprimento' => ''],
                'P'  => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Regata Lumi - Branca',
        'slug' => 'regata-lumi-branca',
        'category' => 'Regatas',
        'price' => 149.90,
        'image' => 'uploads/products/image13.JPG',
        'sizes' => ['P' => 1, 'M' => 1],
        'description' => 'Regata Lumi em branco puro com visual limpo e toque suave. Essencial em qualquer armário inteligente para compor looks elegantes e leves.',
        'composition' => 'Toque de seda com caimento fluido.',
        'care_instructions' => 'Lavar à mão com sabão neutro.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Branca.',
            'rows' => [
                'P' => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '', 'comprimento' => ''],
                'M' => ['busto' => '90 cm', 'cintura' => '79 cm', 'quadril' => '', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Saia Midnight - Branca',
        'slug' => 'saia-midnight-branca',
        'category' => 'Saias',
        'price' => 229.90,
        'image' => 'uploads/products/image14.JPG',
        'sizes' => ['PP' => 2],
        'description' => 'Saia Midnight na cor branca com acabamento delicado e corte fluido. Proporciona movimento elegante e harmonia em composições monocromáticas.',
        'composition' => 'Tecido creponado leve com forro.',
        'care_instructions' => 'Lavar delicadamente. Secar à sombra.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Branca.',
            'rows' => [
                'PP' => ['busto' => '', 'cintura' => '70 cm', 'quadril' => '90 cm', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Saia Midnight - Preta',
        'slug' => 'saia-midnight-preta',
        'category' => 'Saias',
        'price' => 229.90,
        'image' => 'uploads/products/image15.JPG',
        'sizes' => ['PP' => 2, 'P' => 2, 'M' => 2],
        'description' => 'Saia Midnight na cor preta clássica com caimento envolvente e acabamento impecável. Uma peça versátil e atemporal indispensável para diversas ocasiões.',
        'composition' => 'Tecido creponado leve com forro.',
        'care_instructions' => 'Lavar delicadamente. Secar à sombra.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Preta.',
            'rows' => [
                'PP' => ['busto' => '', 'cintura' => '70 cm', 'quadril' => '90 cm', 'comprimento' => ''],
                'P'  => ['busto' => '', 'cintura' => '74 cm', 'quadril' => '94 cm', 'comprimento' => ''],
                'M'  => ['busto' => '', 'cintura' => '79 cm', 'quadril' => '100 cm', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Blusa Clair - Off White',
        'slug' => 'blusa-clair-off-white',
        'category' => 'Blusas',
        'price' => 359.90,
        'image' => 'uploads/products/image16.JPG',
        'sizes' => ['P' => 2, 'M' => 2],
        'description' => 'Blusa Clair em tom off white sofisticado, com modelagem requintada e linhas elegantes. Transmite personalidade e refinamento em todas as produções.',
        'composition' => 'Tecido nobre de toque macio e caimento fluido.',
        'care_instructions' => 'Lavar à mão ou a seco.',
        'measurements' => [
            'note' => 'A modelo veste PP e P. Cor: Off White.',
            'rows' => [
                'P' => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '', 'comprimento' => ''],
                'M' => ['busto' => '90 cm', 'cintura' => '79 cm', 'quadril' => '', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Blusa Clair - Marrom',
        'slug' => 'blusa-clair-marrom',
        'category' => 'Blusas',
        'price' => 359.90,
        'image' => 'uploads/products/image17.JPG',
        'sizes' => ['PP' => 2],
        'description' => 'Blusa Clair na cor marrom profunda com modelagem sofisticada e acabamento de alta alfaiataria. Elegância incomparável com toque aconchegante.',
        'composition' => 'Tecido nobre de toque macio e caimento fluido.',
        'care_instructions' => 'Lavar à mão ou a seco.',
        'measurements' => [
            'note' => 'A modelo veste PP e P. Cor: Marrom.',
            'rows' => [
                'PP' => ['busto' => '82 cm', 'cintura' => '70 cm', 'quadril' => '', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Blusa Aurora - Off White',
        'slug' => 'blusa-aurora-off-white',
        'category' => 'Blusas',
        'price' => 179.90,
        'image' => 'uploads/products/image18.JPG',
        'sizes' => ['PP' => 1, 'M' => 2],
        'description' => 'Blusa Aurora em tom off white com modelagem confortável e gola elegante. Uma peça contemporânea pensada para enriquecer qualquer produção.',
        'composition' => 'Fibras nobres respiráveis com elastano.',
        'care_instructions' => 'Lavar delicadamente em água fria.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Off White.',
            'rows' => [
                'PP' => ['busto' => '82 cm', 'cintura' => '70 cm', 'quadril' => '', 'comprimento' => ''],
                'M'  => ['busto' => '90 cm', 'cintura' => '79 cm', 'quadril' => '', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Blusa Aurora - Verde',
        'slug' => 'blusa-aurora-verde',
        'category' => 'Blusas',
        'price' => 179.90,
        'image' => 'uploads/products/image19.JPG',
        'sizes' => ['PP' => 1, 'P' => 2, 'M' => 2],
        'description' => 'Blusa Aurora em verde refinado com corte preciso e ajuste harmônico. Perfeita para quem valoriza elegância contemporânea com um ponto de cor exclusivo.',
        'composition' => 'Fibras nobres respiráveis com elastano.',
        'care_instructions' => 'Lavar delicadamente em água fria.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Verde.',
            'rows' => [
                'PP' => ['busto' => '82 cm', 'cintura' => '70 cm', 'quadril' => '', 'comprimento' => ''],
                'P'  => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '', 'comprimento' => ''],
                'M'  => ['busto' => '90 cm', 'cintura' => '79 cm', 'quadril' => '', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Vestido Latte',
        'slug' => 'vestido-latte',
        'category' => 'Vestidos',
        'price' => 669.90,
        'image' => 'uploads/products/image20.JPG',
        'sizes' => ['PP' => 1, 'P' => 2],
        'description' => 'Vestido Latte na marcante tonalidade marrom, com linhas fluidas e silhueta feminina bem marcada. Peça autoral sofisticada perfeita para ocasiões especiais.',
        'composition' => 'Tecido acetinado nobre e encorpado.',
        'care_instructions' => 'Limpeza especializada a seco.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Marrom.',
            'rows' => [
                'PP' => ['busto' => '82 cm', 'cintura' => '70 cm', 'quadril' => '90 cm', 'comprimento' => ''],
                'P'  => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '94 cm', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Vestido Bonjour',
        'slug' => 'vestido-bonjour',
        'category' => 'Vestidos',
        'price' => 979.90,
        'image' => 'uploads/products/image21.jpg',
        'sizes' => ['PP' => 2, 'P' => 2],
        'description' => 'Vestido Bonjour em tom off white estonteante com modelagem de alta costura e acabamento impecável. Criado para momentos inesquecíveis com presença inigualável.',
        'composition' => 'Tecido acetinado estruturado com forro nobre.',
        'care_instructions' => 'Limpeza a seco profissional recomendada.',
        'measurements' => [
            'note' => 'A modelo veste PP. Cor: Off White.',
            'rows' => [
                'PP' => ['busto' => '82 cm', 'cintura' => '70 cm', 'quadril' => '90 cm', 'comprimento' => ''],
                'P'  => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '94 cm', 'comprimento' => ''],
            ]
        ]
    ],
    [
        'name' => 'Top Dusk',
        'slug' => 'top-dusk',
        'category' => 'Tops',
        'price' => 59.90,
        'image' => 'uploads/products/image22.JPG',
        'sizes' => ['P' => 2],
        'description' => 'Top Dusk em renda preta com desenho delicado e caimento primoroso. Versátil para compor desde propostas refinadas até produções marcantes de fim de noite.',
        'composition' => 'Renda delicada macia com elastano.',
        'care_instructions' => 'Lavagem manual suave em água fria.',
        'measurements' => [
            'note' => 'A modelo veste P. Cor: Preto de renda.',
            'rows' => [
                'P' => ['busto' => '86 cm', 'cintura' => '74 cm', 'quadril' => '', 'comprimento' => ''],
            ]
        ]
    ],
];

echo "2. Inserindo " . count($catalog) . " produtos oficiais do catálogo...\n";

$insProd = $pdo->prepare(
    'INSERT INTO products (name, slug, category, description, composition, care_instructions, measurements, price, is_new_release, is_active, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())'
);

$insImg = $pdo->prepare(
    'INSERT INTO product_images (product_id, image_url, position) VALUES (?, ?, ?)'
);

$insSize = $pdo->prepare(
    'INSERT INTO product_sizes (product_id, size, stock, position) VALUES (?, ?, ?, ?)'
);

$totalStockInserted = 0;

foreach ($catalog as $idx => $p) {
    $measJson = !empty($p['measurements']) ? json_encode($p['measurements'], JSON_UNESCAPED_UNICODE) : null;
    
    $insProd->execute([
        $p['name'],
        $p['slug'],
        $p['category'],
        $p['description'],
        $p['composition'],
        $p['care_instructions'],
        $measJson,
        $p['price']
    ]);
    
    $productId = (int) $pdo->lastInsertId();
    
    // Inserir imagem principal
    $insImg->execute([$productId, $p['image'], 0]);
    
    // Inserir tamanhos e estoques
    $pos = 0;
    foreach ($p['sizes'] as $sz => $stk) {
        $insSize->execute([$productId, $sz, $stk, $pos++]);
        $totalStockInserted += $stk;
    }
    
    echo sprintf(
        "   [%02d] ID #%02d: %-32s | Cat: %-10s | R$ %8.2f | Tamanhos: %s\n",
        $idx + 1,
        $productId,
        $p['name'],
        $p['category'],
        $p['price'],
        json_encode($p['sizes'])
    );
}

echo "\n[OK] " . count($catalog) . " produtos cadastrados com sucesso!\n";
echo "[OK] Total de unidades em estoque cadastradas: $totalStockInserted unidades.\n";
echo "=== IMPORTAÇÃO CONCLUÍDA COM SUCESSO ===\n";
