<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Flat
 *
 * @ORM\Table(name="flat")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\FlatRepository")
 */
class Flat
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Building")
     *
     */
    private $building;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var integer
     *
     * @ORM\Column(name="surface", type="integer")
     */
    private $surface;

    /**
     * @var string
     *
     * @ORM\Column(name="floor", type="string", length=255, nullable=true)
     */
    private $floor;


    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set building
     *
     * @param string $building
     *
     * @return Flat
     */
    public function setBuilding($building)
    {
        $this->building = $building;

        return $this;
    }

    /**
     * Get building
     *
     * @return string
     */
    public function getBuilding()
    {
        return $this->building;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Flat
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set surface
     *
     * @param string $surface
     *
     * @return Flat
     */
    public function setSurface($surface)
    {
        $this->surface = $surface;

        return $this;
    }

    /**
     * Get surface
     *
     * @return string
     */
    public function getSurface()
    {
        return $this->surface;
    }

    /**
     * @return string
     */
    public function getFloor()
    {
        return $this->floor;
    }

    /**
     * @param string $floor
     * @return Flat
     */
    public function setFloor($floor)
    {
        $this->floor = $floor;
        return $this;
    }


}

