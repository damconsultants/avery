<?php
/**
 * DamConsultants
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the ecomteck.com license that is
 * available through the world-wide-web at this URL:
 *
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    DamConsultants
 * @package     DamConsultants_Avery
 */
namespace DamConsultants\Avery\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class SyncSource implements ArrayInterface
{
    /**
     * To Array
     *
     * @return $this
     */
    public function toOptionArray()
    {
        
        return [
            [
                'value' => 1,
                'label' => __('Manual'),
            ],
            [
                'value' => 2,
                'label' => __('Update By Cron'),
            ]
        ];
    }
}