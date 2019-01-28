<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Linker;

use Doctrine\ORM\Mapping as ORM;

/**
 * Category Model
 *
 *
 * @ORM\Table(name="jtl_connector_link_detail")
 * @ORM\Entity
 * @access public
 */
class DetailLinker
{
    /**
     * @var integer
     *
     * @ORM\Column(name="host_id", type="integer", nullable=false)     
     */
    protected $hostId;

    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\Detail", inversedBy="linker")
     * @ORM\JoinTable(name="s_articles_details", 
     *     joinColumns={@ORM\JoinColumn(name="product_id", referencedColumnName="articleId"),@ORM\JoinColumn(name="detail_id", referencedColumnName="id")}
     * )
     * @ORM\Id
     */
    protected $detail;

    /**
     * Gets the value of detail.
     *
     * @return string
     */
    public function getDetail()
    {
        return $this->detail;
    }

    /**
     * Sets the value of detail.
     *
     * @param detail $detail
     *
     * @return self
     */
    public function setDetail(Detail $detail)
    {
        $this->detail = $detail;

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
