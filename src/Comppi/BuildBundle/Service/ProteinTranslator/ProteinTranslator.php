<?php

namespace Comppi\BuildBundle\Service\ProteinTranslator;

class ProteinTranslator
{
    /**
     * Precedence order of naming conventions.
     * Strongest first.
     * @var array
     */
    private $namingConventionOrder = array(
        'UniProtKB-AC',
        'UniProtKB-ID',
        'EntrezGene',
        'EnsemblPeptideId',
        'EnsemblGeneId',
        'refseq',
        'Hprd',
        'WBGeneId'
    );

    /**
     * @var Doctrine\DBAL\Connection
     */
    private $connection;

    /**
     *
     * @var Comppi\BuildBundle\Service\ProteinTranslator\NameCache
     */
    private $nameCache;

    public function __construct($em) {
        $this->connection = $em->getConnection();
        $this->nameCache = new NameCache();
    }

    /**
     * Gets an existing ComppiId
     *
     * @param string $namingConvention
     * @param string $originalName
     * @param string $specie
     * @return int CommpiId
     */
    public function getComppiId($namingConvention, $originalName, $specie) {
        $comppiId = $this->nameCache->getComppiId($specie, $namingConvention, $originalName);
        if ($comppiId !== false) {
            return $comppiId;
        }

        $translation = $this->getStrongestTranslation($namingConvention, $originalName, $specie);
        $comppiId = $this->getExistingComppiId($translation[0], $translation[1], $specie);

        if ($comppiId === false) {
            $comppiId = $this->insertProtein($translation[0], $translation[1], $specie);
        }

        $this->nameCache->setComppiId($specie, $namingConvention, $originalName, $comppiId);

        return $comppiId;
    }

    /**
     * @param string $namingConvention
     * @param string $proteinName
     * @param string $specie
     *
     * @return array 0 => naming convention; 1 => protein name
     */
    private function getStrongestTranslation($namingConvention, $proteinName, $specie) {
        $mapTableName = 'ProteinNameMap' . ucfirst($specie);

        /**
         * @var \Doctrine\DBAL\Driver\Statement
         */
        $translateStatement = $this->connection->prepare(
        	'SELECT namingConventionB, proteinNameB FROM ' . $mapTableName .
            ' WHERE namingConventionA = ? AND proteinNameA = ?'
        );
        $translateStatement->execute(array($namingConvention, $proteinName));
        $translatedNames = $translateStatement->fetchAll();

        // get strongest translated name
        // init strongest translation as the current one
        $strongestTranslation = array($namingConvention, $proteinName);
        $strongestOrder = array_search($namingConvention, $this->namingConventionOrder);

        // convention not found in the order
        // the fixed weakest order (100) is necessary
        if ($strongestOrder === false) {
            $strongestOrder = 100;
        }

        foreach ($translatedNames as $translatedName) {
            $translatedNameOrder = array_search(
                $translatedName['namingConventionB'],
                $this->namingConventionOrder
            );

            if ($translatedNameOrder < $strongestOrder) {
                $strongestOrder = $translatedNameOrder;
                $strongestTranslation = array(
                    $translatedName['namingConventionB'],
                    $translatedName['proteinNameB']
                );
            }
        }

        if ($strongestTranslation[0] != $namingConvention) {
            // stronger translation found
            // try to get an even more stronger one
            // using recursion
            return $this->getStrongestTranslation(
                $strongestTranslation[0],
                $strongestTranslation[1],
                $specie
            );
        } else {
            // no stronger translation found
            return $strongestTranslation;
        }
    }

    private function getExistingComppiId($namingConvention, $proteinName, $specie) {
        $proteinTableName = 'Protein' . ucfirst($specie);
        /**
         * @var \Doctrine\DBAL\Driver\Statement
         */
        $getIdStatement = $this->connection->prepare(
            'SELECT id FROM ' . $proteinTableName .
            ' WHERE proteinName = ? AND proteinNamingConvention = ?' .
            ' LIMIT 1'
        );
        $getIdStatement->execute(array($proteinName, $namingConvention));

        if ($getIdStatement->rowCount() > 0) {
            $result = $getIdStatement->fetch();

            /** @TODO remove next debug info line */
            //echo 'Existing comppiid found: ' . $result['id'] . "\n";

            return $result['id'];
        } else {
            return false;
        }
    }

    private function insertProtein($namingConvention, $proteinName, $specie) {
        $proteinTableName = 'Protein' . ucfirst($specie);

        $this->connection->executeQuery(
            'INSERT INTO ' .$proteinTableName.
            ' VALUES ("", ?, ?)',
            array($proteinName, $namingConvention)
        );

        return $this->connection->lastInsertId();
    }
}