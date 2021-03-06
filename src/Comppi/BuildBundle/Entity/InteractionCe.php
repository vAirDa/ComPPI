<?php

namespace Comppi\BuildBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class InteractionCe
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

	/**
     * @ORM\Column(type="integer")
     * @ORM\ManyToOne(targetEntity="ProteinCe")
     */
    protected $actorAId;
    
    /**
     * @ORM\Column(type="integer")
     * @ORM\ManyToOne(targetEntity="ProteinCe")
     */
    protected $actorBId;
    
    /**
     * @ORM\Column(type="string", length="255")
     */
    protected $sourceDb;
    
    /**
     * @ORM\Column(type="integer")
     */
    protected $pubmedId;
    
    /**
     * @ORM\Column(type="string", length="255")
     */
    protected $experimentalSystemType;
}