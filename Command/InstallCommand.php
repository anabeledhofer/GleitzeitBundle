<?php

namespace KimaiPlugin\GleitzeitBundle\Command;

use App\Command\AbstractBundleInstallerCommand;

/**
 * "bin/console kimai:bundle:gleitzeit:install" befehl
 * ausführen zum installiern
 * Kimais Basisklasse führt damit die DB Migration aus (erstellt tabellen)
 */
final class InstallCommand extends AbstractBundleInstallerCommand
{
    protected function getBundleCommandNamePart(): string
    {
        // ergibt den Befehlsnamen kimai:bundle:gleitzeit:install
        return 'gleitzeit';
    }

    protected function getMigrationConfigFilename(): ?string
    {
        // zeigt auf unsere Migrations-Konfiguration von oben
        return __DIR__ . '/../Migrations/gleitzeit.yaml'; 
    }
}
