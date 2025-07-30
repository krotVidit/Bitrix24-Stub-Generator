<?php

$sourceDir = 'Путь_до_проекта_/bitrix/modules'; // Дополнительно можно пройтись и по другим директориям
$outputDir = __DIR__ . '/stubs';

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

function parsePhpFile($filePath): ?array
{
    $code = file_get_contents($filePath);
    try {
        $tokens = token_get_all($code);
    } catch (\Throwable $e) {
        return null;
    }

    $classes = [];
    $namespace = '';
    $currentClass = null;
    $visibility = 'public';

    for ($i = 0; $i < count($tokens); $i++) {
        $token = $tokens[$i];

        if (is_array($token)) {
            [$id, $text] = $token;

            if ($id === T_NAMESPACE) {
                $namespace = '';
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NS_SEPARATOR])) {
                        $namespace .= $tokens[$j][1];
                    } elseif ($tokens[$j] === ';') {
                        break;
                    }
                }
            }

            if (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT])) {
                // Проверяем, что через 2 токена есть имя класса
                if (isset($tokens[$i + 2]) && is_array($tokens[$i + 2])) {
                    $className = $tokens[$i + 2][1];
                    $currentClass = $className;
                    $classes[$className] = [
                        'namespace' => $namespace,
                        'methods' => [],
                    ];
                }
            }

            if ($id === T_PUBLIC) {
                $visibility = 'public';
            }

            if ($id === T_FUNCTION) {
                if (isset($tokens[$i + 2]) && is_array($tokens[$i + 2])) {
                    $methodName = $tokens[$i + 2][1];
                    if ($currentClass) {
                        $classes[$currentClass]['methods'][] = [
                            'name' => $methodName,
                            'args' => [],
                            'visibility' => $visibility,
                        ];
                    }
                }
                $visibility = 'public';
            }
        }
    }

    return $classes;
}

function writeStub($className, $classData, $outputDir)
{
    $namespace = $classData['namespace'];
    $methods = $classData['methods'];

    $stub = "<?php\n\n";
    if ($namespace) {
        $stub .= "namespace {$namespace};\n\n";
    }

    $stub .= "class {$className} {\n";

    foreach ($methods as $method) {
        $vis = $method['visibility'] ?? 'public';
        $stub .= "    {$vis} function {$method['name']}() {}\n";
    }

    $stub .= "}\n";

    $filePath = "{$outputDir}/{$className}.php";
    file_put_contents($filePath, $stub);
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir));
foreach ($rii as $file) {
    if (! $file->isDir() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $classes = parsePhpFile($file->getPathname());
        if ($classes) {
            foreach ($classes as $className => $classData) {
                writeStub($className, $classData, $outputDir);
            }
        }
    }
}

echo "✅ Стабы готовы: {$outputDir}\n";
