<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

class ProductAttribute
{
    const TABLE = 'jtl_connector_product_attributes';
    
    /**
     * @var int
     */
    protected $product_id = 0;
    
    /**
     * @var string
     */
    protected $key = '';
    
    /**
     * @var string
     */
    protected $value = '';
    
    /**
     * ProductAttribute constructor.
     * @param int $product_id
     * @param string $key
     * @param string $value
     */
    public function __construct($product_id = 0, $key = '', $value = '')
    {
        $this->product_id = (int) $product_id;
        $this->key = (string) $key;
        $this->value = (string) $value;
    }
    
    /**
     * @return int
     */
    public function getProductId(): int
    {
        return $this->product_id;
    }
    
    /**
     * @param int $product_id
     * @return ProductAttribute
     */
    public function setProductId(int $product_id): ProductAttribute
    {
        $this->product_id = $product_id;
        return $this;
    }
    
    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }
    
    /**
     * @param string $key
     * @return ProductAttribute
     */
    public function setKey(string $key): ProductAttribute
    {
        $this->key = $key;
        return $this;
    }
    
    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }
    
    /**
     * @param string $value
     * @return ProductAttribute
     */
    public function setValue(string $value): ProductAttribute
    {
        $this->value = $value;
        return $this;
    }
    
    /**
     * @return Zend_Db_Adapter_Pdo_Mysql
     */
    private static function db()
    {
        return Shopware()->Db();
    }
    
    /**
     * @param int $product_id
     * @param string $key
     * @return ProductAttribute|null
     */
    public static function get($product_id, $key)
    {
        $result = self::db()->fetchAll(
            'SELECT `product_id`, `key`, `value` FROM ' . self::TABLE . ' WHERE product_id = ? AND key = ?',
            array($product_id, $key)
        );
    
        if (is_array($result) && count($result) > 0) {
            return new self($result[0]['product_id'], $result[0]['key'], $result[0]['value']);
        }
        
        return null;
    }
    
    /**
     * @param bool $delete_first
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function save($delete_first = true)
    {
        if ($this->product_id <= 0 || empty($this->key)) {
            throw new \InvalidArgumentException('Parameter product_id and key must be filled');
        }
        
        if ($delete_first) {
            $this->delete();
        }
        
        $number = self::db()->insert(self::TABLE, array(
            'product_id' => $this->product_id,
            'key' => $this->key,
            'value' => $this->value
        ));
        
        return ($number > 0);
    }
    
    /**
     * @return bool
     */
    public function delete()
    {
        $keys = array($this->product_id);
        $andWhere = '';
        if (strlen($this->key) > 0) {
            $keys[] = $this->key;
            $andWhere = ' AND key = ?';
        }
        
        $sql = 'DELETE FROM ' . self::TABLE . ' WHERE product_id = ?' . $andWhere;
        
        $statement = self::db()->query($sql, $keys);
        
        return ($statement !== false);
    }
}
