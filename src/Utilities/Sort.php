<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2018 JTL-Software GmbH
 *
 * Created at 09.11.2018 08:59
 */

namespace jtl\Connector\Shopware\Utilities;


class Sort
{

    /**
     * @param object[] $elements
     * @param string $sortProperty
     * @param integer $offset
     * @param string $order
     * @return array
     */
    public static function reSort(array $elements, $sortProperty, $offset = 1, $order = 'ASC')
    {
        $getter = 'get' . ucfirst($sortProperty);
        $setter = 'set' . ucfirst($sortProperty);
        $sorted = $elements;
        usort($sorted, function($a, $b) use ($getter){
            if(!method_exists($a, $getter) || !method_exists($b, $getter)) {
                throw new \RuntimeException('Getter ' . $getter . ' does not exist!');
            }
            return $a->$getter() - $b->$getter();
        });

        foreach($sorted as $i => $element) {
            if(!method_exists($element, $setter)) {
                throw new \RuntimeException('Setter ' . $setter . ' does not exist!');
            }
            $element->$setter(($i + $offset));
        }

        if($order === 'DESC') {
            $sorted = array_reverse($sorted);
        }

        return $sorted;
    }
}