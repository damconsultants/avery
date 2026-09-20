<?php

namespace DamConsultants\Avery\Model\ResourceModel;

class BynderDeleteData extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * AverySyc Data
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init('bynder_delete_data', 'id');
    }
}
