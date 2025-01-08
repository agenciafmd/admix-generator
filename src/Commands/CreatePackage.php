<?php

namespace Agenciafmd\Generator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class CreatePackage extends Command
{
    protected $signature = 'admix:create-package';

    protected $description = 'Create a new package';

    protected array $data = [];

    public function handle(): void
    {
        $authorName = $this->ask('What is your name?', $this->processRun('git config user.name'));
        $authorEmail = $this->ask('What is your email?', $this->processRun('git config user.email'));
        $packageVendor = $this->ask('What is your package vendor?', 'Agenciafmd');
        $packageName = $this->ask('What is your package name?', 'Lead');
        $packageName = str($packageName)
            ->ucfirst()
            ->__toString();
        $packageDescription = $this->ask('What is your package about?', $packageName . ' - Agência F&MD');
        $packageIcon = $this->askWithCompletion('What is your package icon? (https://tabler-icons.io/)', ['box']);

        $this->data['authorName'] = $authorName;
        $this->data['authorEmail'] = $authorEmail;

        $this->data['packageVendor'] = str($packageVendor)
            ->lower()
            ->__toString();
        $this->data['packageName'] = str($packageName)
            ->plural()
            ->slug()
            ->__toString();
        $this->data['packageDescription'] = $packageDescription;
        $this->data['packageFriendlyName'] = str($packageName)
            ->plural()
            ->lower()
            ->ucfirst()
            ->__toString();
        $this->data['packageIcon'] = $packageIcon;

        $this->data['namespaceVendor'] = str($packageVendor)
            ->studly()
            ->__toString();
        $this->data['namespaceName'] = str($packageName)
            ->plural()
            ->studly()
            ->__toString();

        $this->data['modelVariableName'] = str($packageName)
            ->singular()
            ->camel()
            ->__toString();
        $this->data['modelName'] = str($packageName)
            ->singular()
            ->studly()
            ->__toString();

        $this->data['className'] = $this->data['modelName'];

        $this->data['routeName'] = str($packageName)
            ->plural()
            ->camel()
            ->__toString();
        $this->data['routePath'] = str($packageName)
            ->plural()
            ->slug()
            ->__toString();
        $this->data['routeModelBind'] = str($packageName)
            ->singular()
            ->camel()
            ->__toString();

        $this->data['migrationTable'] = str($packageName)
            ->plural()
            ->snake()
            ->__toString();
        $this->data['migrationTimestamp'] = now()
            ->format('Y_m_d_His');

        $this->data['directoryName'] = str($packageName)
            ->singular()
            ->slug()
            ->__toString();

        $this->data['viewNamespace'] = 'local-' . str($packageName)
            ->plural()
            ->slug()
            ->__toString();
        $this->data['viewDirectory'] = str($packageName)
            ->plural()
            ->studly()
            ->__toString();

        $this->info('Creating package...');
        $this->updateStubs();

        $this->info('Updating composer.json...');
        $this->updateComposerJson();

        $this->info('Updating composer.lock...');
        $this->updateComposerLock();

        $this->info('Done!');
    }

    private function exportFile(string $source, string $target): void
    {
        $source = __DIR__ . '/../../stubs/' . $source;
        $target = base_path("packages/{$this->data['packageVendor']}/local-{$this->data['packageName']}/{$target}");

        File::ensureDirectoryExists(dirname($target), 0775);
        File::copy($source, $target);

        foreach ($this->data as $key => $value) {
            File::replaceInFile(":{$key}:", $value, $target);
        }
    }

    private function processRun(string $command): string
    {
        $result = Process::run($command);
        if ($result->successful()) {
            return str($result->output())
                ->trim()
                ->__toString();
        }

        return '';
    }

    private function updateComposerJson(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $composer['repositories']["{$this->data['packageVendor']}/local-{$this->data['packageName']}"] = [
            'type' => 'path',
            'url' => "packages/{$this->data['packageVendor']}/local-{$this->data['packageName']}",
            'options' => [
                'symlink' => true,
            ],
        ];
        ksort($composer['repositories']);

        $composer['require']["{$this->data['packageVendor']}/local-{$this->data['packageName']}"] = '*';
        $requirePhp = $composer['require']['php'];
        unset($composer['require']['php']);
        ksort($composer['require']);
        $composer['require'] = array_merge(['php' => $requirePhp], $composer['require']);

        file_put_contents(
            base_path('composer.json'),
            json_encode($composer, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
        );
    }

    private function updateComposerLock(): void
    {
        $this->processRun('composer update');
    }

    private function updateStubs(): void
    {
        $files = File::allFiles(__DIR__ . '/../../stubs/');
        collect($files)
            ->map(function ($path) {
                return $path->getRelativePathname();
            })
            ->mapWithKeys(function ($source) {
                $target = str($source)
                    ->replace(array_keys($this->data), array_values($this->data))
                    ->replaceLast('.stub', '')
                    ->__toString();

                return [
                    $source => $target,
                ];
            })
            ->each(function ($target, $source) {
                $this->exportFile($source, $target);
            });
    }
}
