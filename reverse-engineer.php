<?php
// reverse-engineer.php - Version pour la base starthub

require_once 'vendor/autoload.php';

// Configuration de la base de données - MODIFIEZ CES VALEURS
$dbHost = '127.0.0.1';
$dbName = 'starthub';  // ← Votre base de données
$dbUser = 'root';
$dbPass = '';
$dbPort = 3306;

// Namespace et dossier de sortie
$namespace = 'App\\Entity';
$outputDir = __DIR__ . '/src/Entity';

// Créer le dossier de sortie s'il n'existe pas
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connecté à la base '$dbName' avec succès!\n\n";
} catch (PDOException $e) {
    die("❌ Connexion échouée: " . $e->getMessage() . "\n");
}

// Tables à inclure pour la gestion de projet
$tablesToInclude = [
    'utilisateurs',
    'roles',
    'projets',
    'taches',
    'membres_equipe'
];

// Récupérer toutes les tables
$stmt = $pdo->query("SHOW TABLES");
$allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Filtrer les tables à inclure
$tables = array_intersect($allTables, $tablesToInclude);

if (empty($tables)) {
    echo "❌ Aucune des tables spécifiées n'a été trouvée dans la base '$dbName'.\n";
    echo "Tables trouvées: " . implode(', ', $allTables) . "\n";
    exit(1);
}

echo "📋 Tables à traiter: " . implode(', ', $tables) . "\n\n";

// Stocker les informations des tables
$tableInfo = [];
$foreignKeys = [];

// Premier passage: collecter les infos des tables et clés étrangères
foreach ($tables as $table) {
    echo "🔍 Analyse de la table: $table\n";

    // Convertir le nom de la table en nom de classe
    $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $table)));
    // Enlever le 's' final pour le singulier
    if (substr($className, -1) === 's' && substr($className, -2) !== 'ss') {
        $className = substr($className, 0, -1);
    }

    // Récupérer les colonnes
    $stmt = $pdo->query("DESCRIBE `$table`");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer la clé primaire
    $primaryKey = null;
    foreach ($columns as $column) {
        if ($column['Key'] === 'PRI') {
            $primaryKey = $column['Field'];
            break;
        }
    }

    $tableInfo[$table] = [
        'className' => $className,
        'columns' => $columns,
        'primaryKey' => $primaryKey
    ];

    // Récupérer les clés étrangères
    try {
        $stmt = $pdo->query("
            SELECT 
                COLUMN_NAME, 
                REFERENCED_TABLE_NAME, 
                REFERENCED_COLUMN_NAME
            FROM 
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE 
                TABLE_SCHEMA = '$dbName' AND
                TABLE_NAME = '$table' AND
                REFERENCED_TABLE_NAME IS NOT NULL
        ");

        $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($fks as $fk) {
            $foreignKeys[$table][] = [
                'column' => $fk['COLUMN_NAME'],
                'refTable' => $fk['REFERENCED_TABLE_NAME'],
                'refColumn' => $fk['REFERENCED_COLUMN_NAME']
            ];
        }
    } catch (PDOException $e) {
        echo "⚠️ Attention: Impossible de récupérer les clés étrangères pour $table\n";
    }
}

echo "\n";

// Deuxième passage: générer les entités
foreach ($tables as $table) {
    echo "📝 Génération de l'entité pour: $table\n";

    $className = $tableInfo[$table]['className'];
    $columns = $tableInfo[$table]['columns'];
    $primaryKey = $tableInfo[$table]['primaryKey'];

    // Construction du code de l'entité
    $entityCode = "<?php\n\n";
    $entityCode .= "namespace $namespace;\n\n";
    $entityCode .= "use Doctrine\\ORM\\Mapping as ORM;\n";
    $entityCode .= "use Doctrine\\Common\\Collections\\ArrayCollection;\n";
    $entityCode .= "use Doctrine\\Common\\Collections\\Collection;\n\n";
    $entityCode .= "#[ORM\\Entity(repositoryClass: " . $className . "Repository::class)]\n";
    $entityCode .= "#[ORM\\Table(name: '$table')]\n";
    $entityCode .= "class $className\n";
    $entityCode .= "{\n";

    // Ajouter les propriétés
    foreach ($columns as $column) {
        $fieldName = $column['Field'];
        $fieldType = mapMySQLTypeToPhpType($column['Type']);
        $doctrineType = mapMySQLTypeToDoctrineType($column['Type']);

        // Vérifier si c'est une clé étrangère
        $isForeignKey = false;
        $relationshipCode = "";

        if (isset($foreignKeys[$table])) {
            foreach ($foreignKeys[$table] as $fk) {
                if ($fk['column'] === $fieldName) {
                    $isForeignKey = true;
                    $refTableClassName = $tableInfo[$fk['refTable']]['className'];

                    // Ajouter la relation ManyToOne
                    $relationshipCode .= "    #[ORM\\ManyToOne(targetEntity: $refTableClassName::class)]\n";
                    $relationshipCode .= "    #[ORM\\JoinColumn(name: '$fieldName', referencedColumnName: '{$fk['refColumn']}')]\n";
                    $relationshipCode .= "    private ?$refTableClassName \$" . lcfirst($refTableClassName) . " = null;\n\n";

                    // Getter
                    $relationshipCode .= "    public function get" . $refTableClassName . "(): ?$refTableClassName\n";
                    $relationshipCode .= "    {\n";
                    $relationshipCode .= "        return \$this->" . lcfirst($refTableClassName) . ";\n";
                    $relationshipCode .= "    }\n\n";

                    // Setter
                    $relationshipCode .= "    public function set" . $refTableClassName . "(?$refTableClassName \$" . lcfirst($refTableClassName) . "): self\n";
                    $relationshipCode .= "    {\n";
                    $relationshipCode .= "        \$this->" . lcfirst($refTableClassName) . " = \$" . lcfirst($refTableClassName) . ";\n";
                    $relationshipCode .= "        return \$this;\n";
                    $relationshipCode .= "    }\n\n";

                    break;
                }
            }
        }

        // Si ce n'est pas une clé étrangère, ajouter comme propriété normale
        if (!$isForeignKey) {
            $entityCode .= "    #[ORM\\";

            if ($fieldName === $primaryKey) {
                $entityCode .= "Id]\n";
                $entityCode .= "    #[ORM\\GeneratedValue]\n";
                $entityCode .= "    #[ORM\\Column(type: '$doctrineType')]\n";
            } else {
                $nullable = $column['Null'] === 'YES' ? 'true' : 'false';
                $entityCode .= "Column(type: '$doctrineType', nullable: $nullable)]\n";
            }

            $entityCode .= "    private ?$fieldType \$$fieldName = null;\n\n";

            // Getter
            $getterName = 'get' . ucfirst($fieldName);
            $entityCode .= "    public function $getterName(): ?$fieldType\n";
            $entityCode .= "    {\n";
            $entityCode .= "        return \$this->$fieldName;\n";
            $entityCode .= "    }\n\n";

            // Setter
            $setterName = 'set' . ucfirst($fieldName);
            $entityCode .= "    public function $setterName(";
            if ($column['Null'] === 'YES') {
                $entityCode .= "?";
            }
            $entityCode .= "$fieldType \$$fieldName): self\n";
            $entityCode .= "    {\n";
            $entityCode .= "        \$this->$fieldName = \$$fieldName;\n";
            $entityCode .= "        return \$this;\n";
            $entityCode .= "    }\n\n";
        } else {
            $entityCode .= $relationshipCode;
        }
    }

    // Ajouter les relations OneToMany
    foreach ($tables as $otherTable) {
        if (isset($foreignKeys[$otherTable])) {
            foreach ($foreignKeys[$otherTable] as $fk) {
                if ($fk['refTable'] === $table) {
                    $otherClassName = $tableInfo[$otherTable]['className'];
                    $collectionVar = lcfirst($otherClassName) . 's';
                    $singularVar = lcfirst($otherClassName);

                    $entityCode .= "    /**\n";
                    $entityCode .= "     * @var Collection<int, $otherClassName>\n";
                    $entityCode .= "     */\n";
                    $entityCode .= "    #[ORM\\OneToMany(targetEntity: $otherClassName::class, mappedBy: '" . lcfirst($className) . "')]\n";
                    $entityCode .= "    private Collection \$$collectionVar;\n\n";

                    // Constructeur pour initialiser les collections
                    if (!str_contains($entityCode, 'public function __construct()')) {
                        $entityCode .= "    public function __construct()\n";
                        $entityCode .= "    {\n";
                        $entityCode .= "        \$this->$collectionVar = new ArrayCollection();\n";
                        $entityCode .= "    }\n\n";
                    }

                    // Getter
                    $entityCode .= "    /**\n";
                    $entityCode .= "     * @return Collection<int, $otherClassName>\n";
                    $entityCode .= "     */\n";
                    $entityCode .= "    public function get" . ucfirst($collectionVar) . "(): Collection\n";
                    $entityCode .= "    {\n";
                    $entityCode .= "        return \$this->$collectionVar;\n";
                    $entityCode .= "    }\n\n";

                    // Add method
                    $entityCode .= "    public function add" . ucfirst($singularVar) . "($otherClassName \$$singularVar): self\n";
                    $entityCode .= "    {\n";
                    $entityCode .= "        if (!\$this->$collectionVar->contains(\$$singularVar)) {\n";
                    $entityCode .= "            \$this->$collectionVar->add(\$$singularVar);\n";
                    $entityCode .= "        }\n";
                    $entityCode .= "        return \$this;\n";
                    $entityCode .= "    }\n\n";

                    // Remove method
                    $entityCode .= "    public function remove" . ucfirst($singularVar) . "($otherClassName \$$singularVar): self\n";
                    $entityCode .= "    {\n";
                    $entityCode .= "        \$this->$collectionVar->removeElement(\$$singularVar);\n";
                    $entityCode .= "        return \$this;\n";
                    $entityCode .= "    }\n\n";
                }
            }
        }
    }

    $entityCode .= "}\n";

    // Écrire le fichier
    $filePath = "$outputDir/$className.php";
    file_put_contents($filePath, $entityCode);
    echo "   ✓ Entité générée: $className.php\n";
}

echo "\n✅ Génération des entités terminée !\n";
echo "📁 Entités créées dans: $outputDir\n";
echo "\n▶️ Prochaines étapes:\n";
echo "   1. php bin/console make:entity --regenerate\n";
echo "   2. php bin/console doctrine:schema:validate\n";

// Helper functions
function mapMySQLTypeToPhpType($mysqlType)
{
    if (strpos($mysqlType, 'tinyint(1)') !== false) {
        return 'bool';
    } elseif (strpos($mysqlType, 'int') !== false) {
        return 'int';
    } elseif (strpos($mysqlType, 'float') !== false || strpos($mysqlType, 'double') !== false) {
        return 'float';
    } elseif (strpos($mysqlType, 'decimal') !== false) {
        return 'string';
    } elseif (strpos($mysqlType, 'datetime') !== false || strpos($mysqlType, 'timestamp') !== false) {
        return '\\DateTimeInterface';
    } elseif (strpos($mysqlType, 'date') !== false) {
        return '\\DateTimeInterface';
    } elseif (strpos($mysqlType, 'text') !== false || strpos($mysqlType, 'blob') !== false) {
        return 'string';
    } else {
        return 'string';
    }
}

function mapMySQLTypeToDoctrineType($mysqlType)
{
    if (strpos($mysqlType, 'tinyint(1)') !== false) {
        return 'boolean';
    } elseif (strpos($mysqlType, 'int') !== false) {
        return 'integer';
    } elseif (strpos($mysqlType, 'float') !== false || strpos($mysqlType, 'double') !== false) {
        return 'float';
    } elseif (strpos($mysqlType, 'decimal') !== false) {
        return 'decimal';
    } elseif (strpos($mysqlType, 'datetime') !== false || strpos($mysqlType, 'timestamp') !== false) {
        return 'datetime';
    } elseif (strpos($mysqlType, 'date') !== false) {
        return 'date';
    } elseif (strpos($mysqlType, 'time') !== false) {
        return 'time';
    } elseif (strpos($mysqlType, 'text') !== false) {
        return 'text';
    } elseif (strpos($mysqlType, 'blob') !== false) {
        return 'blob';
    } else {
        return 'string';
    }
}