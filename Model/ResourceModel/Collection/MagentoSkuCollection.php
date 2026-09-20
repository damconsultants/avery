<?php

namespace DamConsultants\Avery\Model\ResourceModel\Collection;

class MagentoSkuCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * MagentoSkuCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Avery\Model\MagentoSku::class,
            \DamConsultants\Avery\Model\ResourceModel\MagentoSku::class
        );
    }
}
