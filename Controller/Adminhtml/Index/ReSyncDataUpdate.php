<?php
namespace DamConsultants\Avery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use DamConsultants\Avery\Controller\Adminhtml\Index\Psku;

class ReSyncDataUpdate extends Action
{
    /**
     * @var \DamConsultants\Avery\Model\BynderConfigSyncDataFactory
     */
    public $bynderSycDataFactory;
    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $_productRepository;
    /**
     * @var \Magento\Catalog\Model\Product\Action
     */
    protected $action;
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManagerInterface;
    /**
     * @var Psku
     */
    protected $pskuController;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param \DamConsultants\Avery\Model\BynderConfigSyncDataFactory $BynderConfigSyncDataFactory
     * @param \Magento\Catalog\Model\Product\Action $action
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param Psku $pskuController
     */
    public function __construct(
        Context $context,
        \DamConsultants\Avery\Model\BynderConfigSyncDataFactory $BynderConfigSyncDataFactory,
        \Magento\Catalog\Model\Product\Action $action,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        Psku $pskuController
    ) {
        $this->bynderSycDataFactory = $BynderConfigSyncDataFactory;
        $this->_productRepository = $productRepository;
        $this->action = $action;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->pskuController = $pskuController;
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('bynder/index/sync');

        $id  = (int)$this->getRequest()->getParam('id');
        $sku = '';

        try {
            $syncModel = $this->bynderSycDataFactory->create()->load($id);
            if (!$syncModel->getId()) {
                $this->messageManager->addErrorMessage(__('Sync record not found.'));
                return $resultRedirect;
            }

            $sku = trim((string)$syncModel->getSku());
            if ($sku === '') {
                $this->messageManager->addErrorMessage(__('This sync record has no SKU.'));
                return $resultRedirect;
            }

            $searchCriteria = $this->searchCriteriaBuilder->addFilter('sku', $sku, 'eq')->create();
            $items = $this->_productRepository->getList($searchCriteria)->getItems();

            if (count($items) === 0) {
                $this->messageManager->addErrorMessage(
                    __('This SKU (%1) is not available in Products List.', $sku)
                );
                return $resultRedirect;
            }

            $response = $this->pskuController->processSkus([$sku], 'all_attribute');

            if ((int)($response['status'] ?? 0) === 1) {
                // Only remove the log row when the resync really worked
                $syncModel->delete();
                $this->messageManager->addSuccessMessage(__('SKU (%1) synced successfully.', $sku));
            } else {
                $this->messageManager->addErrorMessage(
                    __('SKU (%1) sync failed: %2', $sku, $response['message'] ?? 'Unknown error')
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('SKU (%1) sync failed: %2', $sku, $e->getMessage())
            );
        }

        return $resultRedirect;
    }

    /**
     * Is Allowed
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('DamConsultants_Avery::resync');
    }
}