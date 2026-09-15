<?php

namespace KimaiPlugin\GleitzeitBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Legt die Abschluss-Tabelle an (KimaiV1: zeit_konto).
 * Wird durch "bin/console kimai:bundle:gleitzeit:install" ausgeführt.
 */
final class Version20260715120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GleitzeitBundle: Tabelle gleitzeit_abschluss (Monatsabschlüsse)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE gleitzeit_abschluss (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            abgeschlossen_von_id INT NOT NULL,
            jahr INT NOT NULL,
            monat INT NOT NULL,
            uebertrag_sekunden INT NOT NULL,
            uebertrag_reise_sekunden INT NOT NULL,
            urlaub_tage INT NOT NULL,
            urlaub_laufend INT NOT NULL,
            krankenstand_tage INT NOT NULL,
            krankenstand_laufend INT NOT NULL,
            sonderurlaub_tage INT NOT NULL,
            sonderurlaub_laufend INT NOT NULL,
            ho_tage INT NOT NULL,
            reise_tage INT NOT NULL,
            auszahlung_sekunden INT NOT NULL,
            auszahlung_reise_sekunden INT NOT NULL,
            abgeschlossen_am DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_GZ_ABSCHLUSS_USER (user_id),
            UNIQUE INDEX gz_abschluss_unique (user_id, jahr, monat),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Fremdschlüssel: Abschlüsse hängen an Kimai-Usern.
        // ON DELETE CASCADE beim Besitzer: wird der User gelöscht,
        // verschwinden auch seine Abschlüsse.
        $this->addSql('ALTER TABLE gleitzeit_abschluss
            ADD CONSTRAINT FK_GZ_ABSCHLUSS_USER FOREIGN KEY (user_id)
            REFERENCES kimai2_users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gleitzeit_abschluss
            ADD CONSTRAINT FK_GZ_ABSCHLUSS_VON FOREIGN KEY (abgeschlossen_von_id)
            REFERENCES kimai2_users (id)');
    }

    public function down(Schema $schema): void
    {
        // Rückweg für Deinstallation/Tests
        $this->addSql('DROP TABLE gleitzeit_abschluss');
    }
}