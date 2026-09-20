<?php

namespace DamConsultants\Avery\Model\ResourceModel;

class BynderTempData extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * AverySyc Data
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init('bynder_temp_data', 'id');
    }
}
