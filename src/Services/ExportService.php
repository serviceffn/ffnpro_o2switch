<?php
namespace App\Services;

use App\Repository\UsersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\Persistence\ManagerRegistry;

class ExportService extends AbstractController
{
    private $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function exportAll($export, $usersRepository)
    {
        $filename = date('d-m-Y') . '.csv';

        $results = $usersRepository->findByDateDebutAndFin(
            $export->get('dateDebut')->getData(),
            $export->get('dateFin')->getData()
        );
        $now = new \DateTime();

        // Mettre à jour l'en-tête pour inclure 'Type'
        $header_ar = array('Licence;Nom;Prenom;Genre;Age;DateNaissance;Telephone;Email;Centre;Adresse;Complement;Zip;Ville;Pays;Carte;RGPD;Creation;Renouvellement;Type');

        $file = fopen($filename, "w");

        // Ajouter le BOM pour l'encodage UTF-8
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fwrite($file, "\xEF\xBB\xBF");

        // Écrire l'en-tête
        fputcsv($file, explode(';', $header_ar[0]), ';');

        foreach ($results as $result) {
            $associationType = isset($result['association_type']) ? $result['association_type'] : 'N/A';

            $array = [
                $result['n_licence'],
                $result['nom'],
                $result['prenom'],
                $result['genre'],
                (new \DateTime($result['anniversaire']))->diff($now, true)->y,
                (new \DateTime($result['anniversaire']))->format('d-m-Y'),
                $result['telephone'],
                $result['email'],
                $result['nomm'],
                $result['adresse'],
                $result['complement'],
                $result['zip'],
                $result['ville'],
                $result['pays'],
                $result['impression'] == '0' ? 'Non' : 'Oui',
                $result['agree_terms'] == '0' ? 'Non' : 'Oui',
                (new \DateTime($result['created_at']))->format('d-m-Y'),
                $result['renouvellement_at'] == '30-11--0001' ? '-' : (new \DateTime($result['renouvellement_at']))->format('d-m-Y'),
                $associationType, // Ajout de la colonne 'Type'
            ];

            fputcsv($file, $array, ';');
        }

        fclose($file);

        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=" . $filename);
        header("Content-Type: application/csv; ");

        readfile($filename);

        unlink($filename);
        exit();
    }


    public function exportAssoc($exportAssoc, $usersRepository)
    {

        $filename = date('d-m-Y') . '.csv';
        $results = $usersRepository->findByDateDebutAndFinAssoc($exportAssoc->get('dateDebut')->getData(), $exportAssoc->get('dateFin')->getData(), $id = $this->getUser());

        $header_ar = array('Licence;Nom;Prenom;Genre;Age;DateNaissance;Telephone;Email;Centre;Adresse;Complement;Zip;Ville;Pays;Creation;Renouvellement;');

        $now = new \DateTime();

        // $header_ar = explode(', ', $header);

        $file = fopen($filename, "w");
        fputcsv($file, $header_ar);
        foreach ($results as $result) {


            $array = [
                $result['n_licence'],
                $result['nom'],
                $result['prenom'],
                $result['genre'],
                $now->diff($result['anniversaire'], true)->y,
                $result['anniversaire']->format('d-m-Y'),
                $result['telephone'],
                $result['email'],
                $result['nomm'],
                $result['adresse'],
                $result['complement'],
                $result['zip'],
                $result['ville'],
                $result['pays'],
                $result['created_at']->format('d-m-Y'),
                $result['renouvellement_at'] == null ? 'Nouvelle licence' : $result['renouvellement_at']->format('d-m-Y'),
            ];

            fputcsv($file, $array, ';');
        }

        fclose($file);

        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=" . $filename);
        header("Content-Type: application/csv; ");

        readfile($filename);

        unlink($filename);
        exit();
    }

    public function exportAssocRepository($associationsRepository)
    {

        $filename = 'users.csv';
        $results = $associationsRepository->findAll();

        $header_ar = array('Id;Nom;Email;Telephone;Email_President;Telephone_President;Type;Etat');

        // $header_ar = explode(', ', $header);

        // file creation
        $file = fopen($filename, "w");
        fputcsv($file, $header_ar);
        foreach ($results as $result) {


            $array = [
                $result->getId(),
                $result->getNom(),
                $result->getEmail(),
                $result->getTelephoneAssoc(),
                $result->getEmailPresident(),
                $result->getTelephonePresident(),
                $result->getType(),
                $result->getIsActive() == 0 ? 'Inactif' : 'Actif'


            ];

            fputcsv($file, $array, ';');
        }

        fclose($file);

        // download
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=" . $filename);
        header("Content-Type: application/csv; ");

        readfile($filename);

        // deleting file
        unlink($filename);
        exit();
    }

    public function exportRegionsRepository($regionsRepository)
    {

        $filename = 'regions.csv';
        $results = $regionsRepository->findAll();

        $header_ar = array('Id;Region;Nom President;Prenom President;Email President;Telephone Président');

        // $header_ar = explode(', ', $header);

        // file creation
        $file = fopen($filename, "w");
        fputcsv($file, $header_ar);
        foreach ($results as $result) {


            $array = [
                $result->getId(),
                $result->getNom(),
                $result->getNomPresident(),
                $result->getPrenomPresident(),
                $result->getEmailPresident(),
                $result->getTelephonePresident()

            ];

            fputcsv($file, $array, ';');
        }

        fclose($file);

        // download
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=" . $filename);
        header("Content-Type: application/csv; ");

        readfile($filename);

        // deleting file
        unlink($filename);
        exit();
    }

    public function exportAllAssoc($export, $usersRepository, $associationRepository, $associationId, $startingDate, $endingDate)
    {
        $filename = date('d-m-Y') . '_assoc.csv';

        $associationIds = $associationRepository->getAllAssociationIds();
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=" . $filename);
        header("Content-Type: application/csv; charset=UTF-8");
        header('Content-Type: text/html; charset=UTF-8');

        $file = fopen('php://output', 'w');
        if ($file === false) {

            return;
        }

        $header_ar = array('Nom', 'Adulte', 'Homme', 'Femme', 'Enfants', 'Total');
        fputcsv($file, $header_ar, ';');

        foreach ($associationIds as $assocId) {
            $association = $associationRepository->find($assocId);

            $adulteCount = $usersRepository->countAdulteAssocForCsvExport($assocId, $startingDate, $endingDate);
            $hommeCount = $usersRepository->countHommeAssocForCsvExport($assocId, $startingDate, $endingDate);
            $femmeCount = $usersRepository->countFemmeAssocForCsvExport($assocId, $startingDate, $endingDate);
            $allAssoc = $usersRepository->countAllAssocForCsvExport($assocId, $startingDate, $endingDate);
            $enfantsCount = $allAssoc - $adulteCount;

            $totalCount = $adulteCount + $enfantsCount;

            $array = [
                utf8_decode($association->getNom()),
                $adulteCount,
                $hommeCount,
                $femmeCount,
                $enfantsCount,
                $totalCount
            ];

            fputcsv($file, $array, ';');
        }

        fclose($file);

        readfile($filename);

        exit();
    }

    public function exportAllRegions($startingDate, $endingDate): void
    {
        $filename = date('d-m-Y') . '-all-regions.csv';
        // dump($startingDate);
        // dump($endingDate);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $formattedStartDate = $startingDate->format('Y-m-d');
        $formattedEndDate = $endingDate->format('Y-m-d');


        $file = fopen('php://output', 'w');
        if ($file === false) {
            return;
        }

        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $header_ar = ['Région', 'Adultes', 'Hommes', 'Femmes', 'Enfants', 'Total'];
        fputcsv($file, $header_ar, ';');

        $entityManager = $this->managerRegistry->getManager();

        $sql = "SELECT
            r.nom AS nom_region,
            SUM(CASE WHEN TIMESTAMPDIFF(YEAR, u.anniversaire, CURRENT_DATE) >= 18 THEN 1 ELSE 0 END) AS Adultes,
            SUM(CASE WHEN u.genre = 'Masculin' AND TIMESTAMPDIFF(YEAR, u.anniversaire, CURRENT_DATE) >= 18 THEN 1 ELSE 0 END) AS Hommes,
            SUM(CASE WHEN u.genre = 'Feminin' AND TIMESTAMPDIFF(YEAR, u.anniversaire, CURRENT_DATE) >= 18 THEN 1 ELSE 0 END) AS Femmes,
            SUM(CASE WHEN TIMESTAMPDIFF(YEAR, u.anniversaire, CURRENT_DATE) < 18 THEN 1 ELSE 0 END) AS Enfants,
            COUNT(u.id) AS total_licencies
        FROM
            Users u
        JOIN
            Associations a ON u.centre_emetteur_id = a.id
        JOIN
            regions r ON a.region_id = r.id
        WHERE
            u.n_licence LIKE CONCAT(YEAR(CURRENT_DATE), '-%')
            AND (
                (DATE(u.created_at) BETWEEN :start_date AND :end_date)
                OR
                (DATE(u.renouvellement_at) BETWEEN :start_date AND :end_date)
            )
        GROUP BY
            r.nom";

        $statement = $entityManager->getConnection()->prepare($sql);
        $parameters = [
            'start_date' => $formattedStartDate,
            'end_date' => $formattedEndDate,
        ];
        $resultSet = $statement->executeQuery($parameters);


        while ($row = $resultSet->fetchAssociative()) {
            $array = [
                $row['nom_region'],
                $row['Adultes'],
                $row['Hommes'],
                $row['Femmes'],
                $row['Enfants'],
                $row['total_licencies']
            ];

            fputcsv($file, array_map(function ($item) {
                return mb_convert_encoding($item, 'UTF-8', 'auto');
            }, $array), ';');
        }

        fclose($file);
        exit();
    }
    public function exportAllRegionsAndAsssoc($startingDate, $endingDate): void
    {
        $filename = date('d-m-Y') . '-all-regions.csv';
    
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    
        $formattedStartDate = $startingDate->format('Y-m-d');
        $formattedEndDate = $endingDate->format('Y-m-d');
    
        $file = fopen('php://output', 'w');
        if ($file === false) {
            return;
        }
    
        // Ajout du BOM pour l'encodage UTF-8
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
        // Mise à jour des en-têtes pour inclure la colonne Association
        $header_ar = ['Région', 'Association', 'Adultes', 'Hommes', 'Femmes', 'Enfants', 'Total'];
        fputcsv($file, $header_ar, ';');
    
        $entityManager = $this->managerRegistry->getManager();
    
        $sql = "SELECT 
            r.nom AS nom_region, 
            a.nom AS nom_association, 
            SUM(CASE WHEN TIMESTAMPDIFF(YEAR, u.anniversaire, CURRENT_DATE) >= 18 THEN 1 ELSE 0 END) AS Adultes,
            SUM(CASE WHEN u.genre = 'Masculin' AND TIMESTAMPDIFF(YEAR, u.anniversaire, CURRENT_DATE) >= 18 THEN 1 ELSE 0 END) AS Hommes,
            SUM(CASE WHEN u.genre = 'Feminin' AND TIMESTAMPDIFF(YEAR, u.anniversaire, CURRENT_DATE) >= 18 THEN 1 ELSE 0 END) AS Femmes,
            SUM(CASE WHEN TIMESTAMPDIFF(YEAR, u.anniversaire, CURRENT_DATE) < 18 THEN 1 ELSE 0 END) AS Enfants,
            COUNT(u.id) AS total_licencies
        FROM 
            Users u
        JOIN 
            Associations a ON u.centre_emetteur_id = a.id
        JOIN 
            regions r ON a.region_id = r.id
        WHERE 
            u.n_licence LIKE CONCAT(YEAR(CURRENT_DATE), '-%')
            AND (
                (DATE(u.created_at) BETWEEN :start_date AND :end_date)
                OR 
                (DATE(u.renouvellement_at) BETWEEN :start_date AND :end_date)
            )
        GROUP BY 
            r.nom, a.nom
        ORDER BY 
            r.nom, a.nom";
    
        $statement = $entityManager->getConnection()->prepare($sql);
        $parameters = [
            'start_date' => $formattedStartDate,
            'end_date' => $formattedEndDate,
        ];
        $resultSet = $statement->executeQuery($parameters);
    
        // Ajout des données dans le fichier CSV
        while ($row = $resultSet->fetchAssociative()) {
            $array = [
                $row['nom_region'],          // Colonne 'Région'
                $row['nom_association'],     // Colonne 'Association'
                $row['Adultes'],             // Colonne 'Adultes'
                $row['Hommes'],              // Colonne 'Hommes'
                $row['Femmes'],              // Colonne 'Femmes'
                $row['Enfants'],             // Colonne 'Enfants'
                $row['total_licencies']      // Colonne 'Total'
            ];
    
            fputcsv($file, array_map(function ($item) {
                return mb_convert_encoding($item, 'UTF-8', 'auto');
            }, $array), ';');
        }
    
        fclose($file);
        exit();
    }
    

    public function exportAllAssocByRegions($export, $regionsRepository, $regionsId, $regionsName, $startingDate, $endingDate)
    {
        $filename = date('d-m-Y') . '-' . $regionsName . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $file = fopen('php://output', 'w');
        if ($file === false) {
            return;
        }

        // Ajouter le BOM pour UTF-8
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Ajouter l'en-tête CSV
        $header_ar = ['Nom', 'Adultes', 'Hommes', 'Femmes', 'Enfants', 'Total'];
        fputcsv($file, $header_ar, ';');

        $entityManager = $this->managerRegistry->getManager();

        // Requête SQL mise à jour
        $sql = "
            SELECT
                a.nom AS nom_association,
                SUM(CASE WHEN u.genre = 'Masculin' AND TIMESTAMPDIFF(YEAR, u.anniversaire, CURRENT_DATE) >= 18 THEN 1 ELSE 0 END) AS Hommes,
                SUM(CASE WHEN u.genre = 'Feminin' AND TIMESTAMPDIFF(YEAR, u.anniversaire, CURRENT_DATE) >= 18 THEN 1 ELSE 0 END) AS Femmes,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, u.anniversaire, CURRENT_DATE) >= 18 THEN 1 ELSE 0 END) AS Adultes,
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, u.anniversaire, CURRENT_DATE) < 18 THEN 1 ELSE 0 END) AS Enfants,
                COUNT(u.id) AS total_licencies
            FROM
                Users u
            JOIN
                Associations a ON u.centre_emetteur_id = a.id
            JOIN
                regions r ON a.region_id = r.id
            WHERE
                r.id = :region_id
                AND u.n_licence LIKE CONCAT(YEAR(CURRENT_DATE), '-%')
                AND (
                    (DATE(u.created_at) BETWEEN :start_date AND :end_date)
                    OR
                    (DATE(u.renouvellement_at) BETWEEN :start_date AND :end_date)
                )
            GROUP BY
                a.nom
        ";

        $statement = $entityManager->getConnection()->prepare($sql);
        $parameters = [
            'region_id' => $regionsId,
            'start_date' => $startingDate,
            'end_date' => $endingDate,
        ];
        $resultSet = $statement->executeQuery($parameters);

        while ($row = $resultSet->fetchAssociative()) {
            $array = [
                $row['nom_association'],
                $row['Adultes'],
                $row['Hommes'],
                $row['Femmes'],
                $row['Enfants'],
                $row['total_licencies']
            ];

            fputcsv($file, array_map(function ($item) {
                return mb_convert_encoding($item, 'UTF-8', 'auto');
            }, $array), ';');
        }

        fclose($file);
        exit();
    }


    public function exportAllRegionsLicenceSeller($usersRepository, $startingDate, $endingDate)
    {
        $filename = date('d-m-Y') . '_LicencesRégionales.csv';

        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=" . $filename);
        header("Content-Type: application/csv; charset=UTF-8");
        header('Content-Type: text/html; charset=UTF-8');

        $file = fopen('php://output', 'w');
        if ($file === false) {
            return;
        }

        $header_ar = array('Region', 'Adulte', 'Homme', 'Femme', 'Enfants', 'Total');
        fputcsv($file, $header_ar, ';');

        $centreEmetteurNames = $usersRepository->getCentreEmetteurNames();
        foreach ($centreEmetteurNames as $region) {
            $adulteCount = $usersRepository->countAdulteAssocForRegionLicenceSeller($region['id'], $startingDate, $endingDate);
            $hommeCount = $usersRepository->countHommeAssocForRegionLicenceSeller($region['id'], $startingDate, $endingDate);
            $femmeCount = $usersRepository->countFemmeAssocForRegionLicenceSeller($region['id'], $startingDate, $endingDate);
            $allAssoc = $usersRepository->countAllAssocForRegionLicenceSeller($region['id'], $startingDate, $endingDate);
            $enfantsCount = $allAssoc - $adulteCount;
            $totalCount = $adulteCount + $enfantsCount;

            $array = [
                $region['nom'],
                $adulteCount,
                $hommeCount,
                $femmeCount,
                $enfantsCount,
                $totalCount
            ];

            fputcsv($file, $array, ';');
        }

        fclose($file);
    }

    public function exportAllAssocByCentres($export, $startingDate, $endingDate)
    {
        $filename = date('d-m-Y') . '-Centres' . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $file = fopen('php://output', 'w');
        if ($file === false) {
            return;
        }

        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $header_ar = array('Nom', 'Adultes', 'Hommes', 'Femmes', 'Enfants', 'Total');
        fputcsv($file, $header_ar, ';');

        $entityManager = $this->managerRegistry->getManager();

        $sql = "SELECT
    a.nom AS nom_association,
    COUNT(*) AS Adultes,
    COUNT(CASE WHEN u.genre = 'Masculin' THEN 1 END) AS Hommes,
    COUNT(CASE WHEN u.genre = 'Feminin' THEN 1 END) AS Femmes,
    COUNT(CASE WHEN YEAR(CURRENT_DATE()) - YEAR(u.anniversaire) < 18 THEN 1 END) AS Enfants,
    COUNT(*) AS total_licencies
    FROM
        Users u
    JOIN
        Associations a ON u.centre_emetteur_id = a.id
    WHERE
        u.n_licence LIKE CONCAT(YEAR(CURRENT_DATE()), '-%')
    AND a.type = 'Centre'
    AND (
        (DATE(u.created_at) BETWEEN :start_date AND :end_date)
        OR
        (DATE(u.renouvellement_at) BETWEEN :start_date AND :end_date)
    )
    GROUP BY
    a.nom";

        $statement = $entityManager->getConnection()->prepare($sql);
        $parameters = [
            'start_date' => $startingDate,
            'end_date' => $endingDate,
        ];
        $resultSet = $statement->executeQuery($parameters);

        while ($row = $resultSet->fetchAssociative()) {
            $array = [
                $row['nom_association'],
                $row['Adultes'],
                $row['Hommes'],
                $row['Femmes'],
                $row['Enfants'],
                $row['total_licencies']
            ];

            fputcsv($file, array_map(function ($item) {
                return mb_convert_encoding($item, 'UTF-8', 'auto');
            }, $array), ';');
        }

        fclose($file);

        exit();
    }


}