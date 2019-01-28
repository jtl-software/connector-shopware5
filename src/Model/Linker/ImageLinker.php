<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Linker;

use Doctrine\ORM\Mapping as ORM;

/**
 * Image Model
 *
 *
 * @ORM\Table(name="jtl_connector_link_image")
 * @ORM\Entity
 * @access public
 */
class ImageLinker
{
    /**
     * @var integer
     *
     * @ORM\Column(name="host_id", type="integer", nullable=false)
     */
    protected $hostId;

    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\Image", inversedBy="linker")
     * @ORM\JoinColumn(name="media_id", referencedColumnName="id")
     * @ORM\Id
     */
    protected $image;

    /**
     * Gets the value of image.
     *
     * @return string
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * Sets the value of image.
     *
     * @param Image $image
     *
     * @return self
     */
    public function setImage(Image $image)
    {
        $this->image = $image;

        return $this;
    }

    /**
     * Gets the value of hostId.
     *
     * @return integer
     */
    public function getHostId()
    {
        return $this->hostId;
    }

    /**
     * Sets the value of hostId.
     *
     * @param integer $hostId the host id
     *
     * @return self
     */
    public function setHostId($hostId)
    {
        $this->hostId = $hostId;

        return $this;
    }
}
