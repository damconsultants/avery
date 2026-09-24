<?php

namespace DamConsultants\Avery\Controller\Adminhtml\Index;

use DamConsultants\Avery\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;
use DamConsultants\Avery\Model\ResourceModel\Collection\BynderMediaTableCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;

class Psku extends \Magento\Backend\App\Action
{
    /**
     * Roles assigned to the FIRST image when Bynder returns no role at all.
     * Change these labels to match the exact options of your magento_role attribute.
     */
    private const DEFAULT_IMAGE_ROLES = ['Base', 'Small', 'Thumbnail'];

    /**
     * Used when no select_attribute is given (e.g. ReSync link).
     */
    private const DEFAULT_SELECT_ATTRIBUTE = 'all_attribute';

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory = false;
    /**
     * @var \DamConsultants\Avery\Model\BynderMediaTableFactory
     */
    protected $bynderMediaTable;
    /**
     * @var BynderMediaTableCollectionFactory
     */
    protected $bynderMediaTableCollectionFactory;
    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $_productRepository;
    /**
     * @var \DamConsultants\Avery\Helper\Data
     */
    protected $datahelper;
    /**
     * @var \Magento\Catalog\Model\Product\Action
     */
    protected $productAction;
    /**
     * @var \DamConsultants\Avery\Model\BynderConfigSyncDataFactory
     */
    protected $_byndersycData;
    /**
     * @var MetaPropertyCollectionFactory
     */
    protected $metaPropertyCollectionFactory;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManagerInterface;
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;
    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $product;

    /**
     * Product Sku.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Catalog\Model\Product\Action $action
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \DamConsultants\Avery\Model\BynderConfigSyncDataFactory $byndersycData
     * @param \DamConsultants\Avery\Model\BynderMediaTableFactory $bynderMediaTable
     * @param BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param \DamConsultants\Avery\Helper\Data $DataHelper
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Framework\App\ResourceConnection $resource
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Catalog\Model\Product\Action $action,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Avery\Model\BynderConfigSyncDataFactory $byndersycData,
        \DamConsultants\Avery\Model\BynderMediaTableFactory $bynderMediaTable,
        BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory,
        \Magento\Catalog\Model\Product $product,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        \DamConsultants\Avery\Helper\Data $DataHelper,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Framework\App\ResourceConnection $resource
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $jsonFactory;
        $this->productAction = $action;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->datahelper = $DataHelper;
        $this->bynderMediaTable = $bynderMediaTable;
        $this->bynderMediaTableCollectionFactory = $bynderMediaTableCollectionFactory;
        $this->_byndersycData = $byndersycData;
        $this->_productRepository = $productRepository;
        $this->product = $product;
    }

    /**
     * Execute (AJAX from admin grid)
     *
     * @return \Magento\Framework\Controller\Result\Json|string
     */
    public function execute()
    {
        if (!$this->getRequest()->isAjax()) {
            $this->_forward('noroute');
            return '';
        }

        $result          = $this->resultJsonFactory->create();
        $productSku      = trim((string)$this->getRequest()->getParam('product_sku'));
        $selectAttribute = (string)$this->getRequest()->getParam('select_attribute');

        // Split, trim and drop empty entries (e.g. "SKU1,,SKU2, ")
        $skus = array_values(array_filter(
            array_map('trim', explode(',', $productSku)),
            'strlen'
        ));

        if (empty($skus)) {
            return $result->setData([
                'status'  => 0,
                'message' => 'Please enter at least one SKU.'
            ]);
        }

        return $result->setData($this->processSkus($skus, $selectAttribute));
    }

    /**
     * Sync each SKU with the Avery / Bynder API.
     * Can be called from other controllers (e.g. ReSyncDataUpdate).
     *
     * @param string[]    $productSkus
     * @param string|null $selectAttribute image | video | document | all_attribute
     * @return array ['status' => int, 'message' => string]
     */
    public function processSkus(array $productSkus, ?string $selectAttribute = self::DEFAULT_SELECT_ATTRIBUTE): array
    {
        $selectAttribute = trim((string)$selectAttribute);
        if ($selectAttribute === '') {
            $selectAttribute = self::DEFAULT_SELECT_ATTRIBUTE;
        }

        // Load meta properties once for all SKUs
        $metaProperties = $this->getMetaPropertiesCollection(
            $this->metaPropertyCollectionFactory->create()->getData()
        );
        $collectionValue   = $metaProperties['collection_data_value'];
        $collectionSlugVal = $metaProperties['collection_data_slug_val'];

        $hasErrors = false;

        foreach ($productSkus as $sku) {
            $sku = trim((string)$sku);
            if ($sku === '') {
                continue;
            }

            // 1. Make sure the product exists
            try {
                $productId = $this->product->getIdBySku($sku);
            } catch (NoSuchEntityException $e) {
                $productId = false;
            }
            if (!$productId) {
                $this->insertLog($sku, 'SKU not found in products');
                $hasErrors = true;
                continue;
            }

            // 2. Call the API
            $byder_sku   = $sku;
            $getData = $this->datahelper->getImageSyncWithProperties($byder_sku, null, $collectionValue);

            if (empty($getData) || !$this->getIsJSON($getData)) {
                $this->insertLog($sku, 'Invalid response received from API');
                return [
                    'status'  => 0,
                    'message' => 'Something went wrong from API side, please contact support team!'
                ];
            }

            $response = json_decode($getData, true);
            if ((int)($response['status'] ?? 0) !== 1) {
                $this->insertLog($sku, 'Please Select The Metaproperty First.....');
                return [
                    'status'  => 0,
                    'message' => 'Please check Avery Synchronization. Action Log.....'
                ];
            }

            // 3. Handle the per-SKU payload ("data" may be a JSON string or an array)
            $rawData      = $response['data'] ?? '';
            $convertArray = is_array($rawData) ? $rawData : json_decode((string)$rawData, true);
            if (!is_array($convertArray)) {
                $this->insertLog($sku, 'Invalid data received from API');
                $hasErrors = true;
                continue;
            }

            if ((int)($convertArray['status'] ?? 0) === 1) {
                try {
                    if (!$this->getDataItem($selectAttribute, $convertArray, $collectionSlugVal, $sku)) {
                        $hasErrors = true;
                    }
                } catch (\Exception $e) {
                    $this->insertLog($sku, $e->getMessage());
                    $hasErrors = true;
                }
            } else {
                $message = $convertArray['data'] ?? 'Unknown error';
                $this->insertLog($sku, is_string($message) ? $message : json_encode($message));
                $this->resetProductAttributes($sku);
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            return [
                'status'  => 0,
                'message' => 'Sync finished with errors. Please check AverySynchronization Log.'
            ];
        }

        return [
            'status'  => 1,
            'message' => 'Data Sync Successfully. Please check AverySynchronization Log.!'
        ];
    }

    /**
     * Insert log data
     *
     * @param string $sku
     * @param string $message
     */
    private function insertLog(string $sku, string $message): void
    {
        $this->getInsertDataTable([
            'sku'         => $sku,
            'message'     => $message,
            'data_type'   => '',
            'sync_source' => '1',
            'lable'       => '0'
        ]);
    }

    /**
     * Reset product attributes when sync fails
     *
     * @param string $sku
     */
    private function resetProductAttributes(string $sku): void
    {
        $product_id = $this->product->getIdBySku($sku);
        if (!$product_id) {
            return;
        }

        $updated_values = [
            'bynder_multi_img' => null,
            'bynder_isMain'    => null
        ];
        $storeId = $this->storeManagerInterface->getStore()->getId();
        $this->productAction->updateAttributes([$product_id], $updated_values, $storeId);
    }

    /**
     * Get Meta Properties Collection
     *
     * @param array $collection
     * @return array
     */
    public function getMetaPropertiesCollection($collection)
    {
        $collection_data_value = [];
        $collection_data_slug_val = [];
        if (is_array($collection) && count($collection) >= 1) {
            foreach ($collection as $collection_value) {
                $collection_data_value[] = [
                    'id' => $collection_value['id'],
                    'property_name' => $collection_value['property_name'],
                    'property_id' => $collection_value['property_id'],
                    'magento_attribute' => $collection_value['magento_attribute'],
                    'attribute_id' => $collection_value['attribute_id'],
                    'bynder_property_slug' => $collection_value['bynder_property_slug'],
                    'system_slug' => $collection_value['system_slug'],
                    'system_name' => $collection_value['system_name']
                ];
                $collection_data_slug_val[$collection_value['system_slug']] = [
                    'bynder_property_slug' => $collection_value['bynder_property_slug'],
                ];
            }
        }
        return [
            'collection_data_value' => $collection_data_value,
            'collection_data_slug_val' => $collection_data_slug_val
        ];
    }

    /**
     * Is Json
     *
     * @param string $string
     * @return bool
     */
    public function getIsJSON($string)
    {
        return json_decode((string)$string) !== null;
    }

    /**
     * Normalize image role values returned by Bynder.
     *
     * @param mixed $role
     * @return string
     */
    private function normalizeMagentoRole($role): string
    {
        if (is_array($role)) {
            return '';
        }

        $role = trim((string)$role);
        if ($role === '') {
            return '';
        }

        if (strtolower($role) === 'thumb') {
            return 'Thumbnail';
        }

        if ((string)(int)$role === $role) {
            return (int)$role === 0 ? 'Base' : '';
        }

        return $role;
    }

    /**
     * Drop placeholder / empty values from a role list.
     *
     * @param mixed $roles
     * @return array
     */
    private function sanitizeImageRoles($roles): array
    {
        if (!is_array($roles)) {
            $roles = ($roles === null || $roles === '') ? [] : [$roles];
        }

        $clean = [];
        foreach ($roles as $role) {
            if (is_array($role)) {
                continue;
            }
            $role = trim((string)$role);
            if ($role !== '' && $role !== '###') {
                $clean[] = $role;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * Prepare image role, alt text, and media id values from the API response.
     * Always returns exactly ONE alt text and ONE media id per item, so the
     * values stay aligned with the item URLs later on.
     *
     * @param array $imageData
     * @param string|int $bynderMediaId
     * @param bool $applyDefaultRole Assign the static roles when the API returns none.
     * @return array
     */
    private function prepareImageMetadata(array $imageData, $bynderMediaId, bool $applyDefaultRole = false): array
    {
        $roleOptions = $imageData['magento_role_options'] ?? [];
        $roleDetails = $imageData['magento_role_options_details'] ?? [];

        if (!is_array($roleOptions)) {
            $roleOptions = [];
        }
        if (!is_array($roleDetails)) {
            $roleDetails = [];
        }

        $roles = [];
        if (count($roleOptions) > 0) {
            foreach ($roleOptions as $index => $roleOption) {
                $normalizedRole = $this->normalizeMagentoRole($roleOption);
                if ($normalizedRole === '' && isset($roleDetails[$index]['label'])) {
                    $normalizedRole = trim((string)$roleDetails[$index]['label']);
                }
                if ($normalizedRole === '' && isset($roleDetails[$index]['slug'])) {
                    $normalizedRole = trim((string)$roleDetails[$index]['slug']);
                }
                $roles[] = $normalizedRole;
            }
        } elseif (count($roleDetails) > 0) {
            foreach ($roleDetails as $detail) {
                $normalizedRole = trim((string)($detail['label'] ?? ''));
                if ($normalizedRole === '') {
                    $normalizedRole = trim((string)($detail['slug'] ?? ''));
                }
                $roles[] = $normalizedRole;
            }
        }

        $roles = $this->sanitizeImageRoles($roles);
        if (empty($roles)) {
            $roles = $applyDefaultRole ? self::DEFAULT_IMAGE_ROLES : ['###'];
        }

        // Alt text must stay on ONE line, otherwise it breaks the "\n" alignment.
        $altTextValue = $imageData['img_alt_text'] ?? '';
        if (is_array($altTextValue)) {
            $altTextValue = implode(' ', $altTextValue);
        }
        $altTextValue = trim(preg_replace('/\s+/', ' ', (string)$altTextValue));

        return [
            'roles'     => $roles,
            'alt_text'  => [($altTextValue !== '' ? $altTextValue : '###') . "\n"],
            'media_ids' => [$bynderMediaId],
        ];
    }

    /**
     * Insert a row in the synchronization log table
     *
     * @param array $insert_data
     */
    public function getInsertDataTable($insert_data)
    {
        $model = $this->_byndersycData->create();
        $model->setData([
            'sku' => $insert_data['sku'],
            'bynder_sync_data' => $insert_data['message'],
            'bynder_data_type' => $insert_data['data_type'],
            'sync_source' => $insert_data['sync_source'],
            'lable' => $insert_data['lable']
        ]);
        $model->save();
    }

    /**
     * Insert new media ids for a SKU in the media table
     *
     * @param string $sku
     * @param array $m_id
     * @param string|int $product_ids
     * @param string|int $storeId
     */
    public function getInsertMedaiDataTable($sku, $m_id, $product_ids, $storeId)
    {
        $modelcollection = $this->bynderMediaTableCollectionFactory->create();
        $modelcollection->addFieldToFilter('sku', ['eq' => [$sku]])->load();
        $table_m_id = [];
        foreach ($modelcollection as $mdata) {
            $table_m_id[] = $mdata['media_id'];
        }

        $media_diff = array_diff((array)$m_id, $table_m_id);
        foreach ($media_diff as $new_data) {
            $new_m_id = trim((string)$new_data);
            if ($new_m_id === '') {
                continue;
            }
            $model = $this->bynderMediaTable->create();
            $model->setData([
                'sku' => $sku,
                'media_id' => $new_m_id,
                'status' => '1',
            ]);
            $model->save();
        }

        $this->productAction->updateAttributes(
            [$product_ids],
            ['bynder_delete_cron' => 1],
            $storeId
        );
    }

    /**
     * Delete media rows of a SKU that do not match the given media id
     *
     * @param string $sku
     * @param string $media_id
     */
    public function getDeleteMedaiDataTable($sku, $media_id)
    {
        $model = $this->bynderMediaTableCollectionFactory->create()
            ->addFieldToFilter('sku', ['eq' => [$sku]])
            ->load();
        foreach ($model as $mdata) {
            if ($mdata['media_id'] != $media_id) {
                $this->bynderMediaTable->create()->load($mdata['id'])->delete();
            }
        }
    }

    /**
     * Build the item lists from the API data and save them on the product.
     *
     * @param string $select_attribute
     * @param array $convert_array
     * @param array $collection_data_slug_val
     * @param string $current_sku
     * @return bool true when everything was saved
     */
    public function getDataItem($select_attribute, $convert_array, $collection_data_slug_val, $current_sku)
    {
        $data_arr     = [];
        $data_val_arr = [];
        $doc_data_arr = [];
        $doc_val_arr  = [];

        // The first image of this SKU gets the static roles when the API sends none.
        $is_first_image = true;

        if ((int)($convert_array['status'] ?? 0) !== 1 || !is_array($convert_array['data'] ?? null)) {
            $this->insertLog($current_sku, 'No Data Found...');
            return false;
        }

        foreach ($convert_array['data'] as $data_value) {
            $item_type = $data_value['type'] ?? '';

            if ($select_attribute !== 'all_attribute' && $select_attribute != $item_type) {
                continue;
            }

            $image_data = is_array($data_value['thumbnails'] ?? null) ? $data_value['thumbnails'] : [];
            $is_image_item = ($item_type === 'image');

            $imageMetadata = $this->prepareImageMetadata(
                $image_data,
                $data_value['id'] ?? '',
                $is_image_item && $is_first_image
            );
            if ($is_image_item) {
                $is_first_image = false;
            }

            if ($item_type === 'image') {
                $url  = [($image_data['Hi_Res_JPG'] ?? '') . "\n"];
                $type = 'image';
            } elseif ($item_type === 'video') {
                $video_link = ($data_value['videoPreviewURLs'][0] ?? '') . '@@' . ($image_data['webimage'] ?? '');
                $url  = [$video_link . "\n"];
                $type = 'video';
            } else {
                $doc_name = preg_replace('/[^a-zA-Z]+/', '-', (string)($data_value['name'] ?? ''));
                $url  = [($data_value['original'] ?? '') . '@@' . $doc_name . "\n"];
                $type = 'doc';
            }

            $data_p = [
                'sku' => $current_sku,
                'url' => $url,
                'magento_image_role' => $imageMetadata['roles'],
                'image_alt_text' => $imageMetadata['alt_text'],
                'bynder_media_id_new' => $imageMetadata['media_ids']
            ];
            if ($type !== 'image') {
                $data_p['type'] = $type;
            }

            // Documents go to bynder_document, images/videos to bynder_multi_img
            if ($type === 'doc') {
                $doc_data_arr[] = $current_sku;
                $doc_val_arr[]  = $data_p;
            } else {
                $data_arr[]     = $current_sku;
                $data_val_arr[] = $data_p;
            }
        }

        if (empty($data_arr) && empty($doc_data_arr)) {
            $this->insertLog($current_sku, 'No Data Found...');
            return false;
        }

        $ok = true;
        if (!empty($data_arr)) {
            $ok = $this->getProcessItem($data_arr, $data_val_arr, $select_attribute) && $ok;
        }
        if (!empty($doc_data_arr)) {
            $ok = $this->getProcessItemDoc($doc_data_arr, $doc_val_arr, $select_attribute) && $ok;
        }
        return $ok;
    }

    /**
     * Group item rows by SKU.
     *
     * @param array $data_arr
     * @param array $data_val_arr
     * @return array
     */
    private function groupItemsBySku(array $data_arr, array $data_val_arr): array
    {
        $grouped = [];
        foreach ($data_arr as $key => $sku) {
            if (!isset($data_val_arr[$key])) {
                continue;
            }
            $sku  = (string)$sku;
            $item = $data_val_arr[$key];
            if (!isset($grouped[$sku])) {
                $grouped[$sku] = ['urls' => '', 'roles' => [], 'alt_text' => '', 'media_ids' => []];
            }
            $grouped[$sku]['urls']       .= implode('', $item['url']);
            $grouped[$sku]['roles'][]     = $item['magento_image_role'];
            $grouped[$sku]['alt_text']   .= implode('', $item['image_alt_text']);
            $grouped[$sku]['media_ids'][] = implode('', $item['bynder_media_id_new']);
        }
        return $grouped;
    }

    /**
     * Process images / videos
     *
     * @param array $data_arr
     * @param array $data_val_arr
     * @param string|null $select_attribute
     * @return bool
     */
    public function getProcessItem($data_arr, $data_val_arr, $select_attribute = null)
    {
        $ok = true;
        foreach ($this->groupItemsBySku($data_arr, $data_val_arr) as $sku => $group) {
            $sku = (string)$sku;
            $ok = $this->getUpdateImage(
                $group['urls'],
                $sku,
                $group['roles'],
                $group['alt_text'],
                [$sku => $group['media_ids']],
                $select_attribute
            ) && $ok;
        }
        return $ok;
    }

    /**
     * Process documents
     *
     * @param array $data_arr
     * @param array $data_val_arr
     * @param string|null $select_attribute
     * @return bool
     */
    public function getProcessItemDoc($data_arr, $data_val_arr, $select_attribute = null)
    {
        $ok = true;
        foreach ($this->groupItemsBySku($data_arr, $data_val_arr) as $sku => $group) {
            $sku = (string)$sku;
            $ok = $this->getUpdateDoc(
                $group['urls'],
                $sku,
                $group['roles'],
                $group['alt_text'],
                [$sku => $group['media_ids']],
                $select_attribute
            ) && $ok;
        }
        return $ok;
    }

    /**
     * Resolve select_attribute: argument first, then request, then default.
     *
     * @param string|null $select_attribute
     * @return string
     */
    private function resolveSelectAttribute($select_attribute): string
    {
        $value = trim((string)$select_attribute);
        if ($value === '') {
            $value = trim((string)$this->getRequest()->getParam('select_attribute'));
        }
        return $value !== '' ? $value : self::DEFAULT_SELECT_ATTRIBUTE;
    }

    /**
     * Alt text for a given index ('' when missing or placeholder).
     *
     * @param array $altTexts
     * @param int $index
     * @return string
     */
    private function getAltTextValue(array $altTexts, $index): string
    {
        $value = trim((string)($altTexts[$index] ?? ''));
        return ($value === '' || $value === '###') ? '' : $value;
    }

    /**
     * A role can belong to one image only: remove the given roles from
     * the other images in the list.
     *
     * @param array $items
     * @param array $roles
     * @param bool $skipLast keep the last item untouched (the one just added)
     * @return array
     */
    private function removeRolesFromOthers(array $items, array $roles, bool $skipLast = true): array
    {
        if (empty($roles)) {
            return $items;
        }
        $keys    = array_keys($items);
        $lastKey = end($keys);
        foreach ($items as $idx => $item) {
            if ($skipLast && $idx === $lastKey) {
                continue;
            }
            if (($item['item_type'] ?? '') !== 'IMAGE') {
                continue;
            }
            if (!empty($item['image_role']) && is_array($item['image_role'])) {
                // array_values keeps it a JSON list, not an object
                $items[$idx]['image_role'] = array_values(array_diff($item['image_role'], $roles));
            }
        }
        return $items;
    }

    /**
     * Write ONE log row for a SKU with all item URLs comma-separated.
     *
     * @param string $sku
     * @param array $items
     * @param string $dataType 1 = image, 2 = document, 3 = video
     */
    private function logItemsForSku($sku, array $items, string $dataType): void
    {
        $urls = array_filter(array_map('trim', array_column($items, 'item_url')), 'strlen');
        if (empty($urls)) {
            return;
        }
        $this->getInsertDataTable([
            'sku'         => (string)$sku,
            'message'     => implode(',', array_unique($urls)),
            'data_type'   => $dataType,
            'sync_source' => '1',
            'lable'       => '1'
        ]);
    }

    /**
     * bynder_isMain flag: 1 = image + video, 2 = image only, 3 = video only
     *
     * @param array $items
     * @return int
     */
    private function getMediaFlag(array $items): int
    {
        $types = array_column($items, 'item_type');
        $hasImage = in_array('IMAGE', $types, true);
        $hasVideo = in_array('VIDEO', $types, true);
        if ($hasImage && $hasVideo) {
            return 1;
        }
        if ($hasImage) {
            return 2;
        }
        if ($hasVideo) {
            return 3;
        }
        return 0;
    }

    /**
     * Save bynder_multi_img + media table for a product.
     *
     * @param string $sku
     * @param int|string $productId
     * @param int|string $storeId
     * @param array $items
     * @param bool $cleanMediaTable
     */
    private function saveMultiImg($sku, $productId, $storeId, array $items, bool $cleanMediaTable): void
    {
        $media_ids = [];
        foreach ($items as $item) {
            $media_ids[] = $item['bynder_md_id'];
            if ($cleanMediaTable) {
                $this->getDeleteMedaiDataTable($sku, $item['bynder_md_id']);
            }
        }
        $this->getInsertMedaiDataTable($sku, $media_ids, $productId, $storeId);

        $this->productAction->updateAttributes(
            [$productId],
            [
                'bynder_multi_img' => json_encode(array_values($items)),
                'bynder_isMain'    => $this->getMediaFlag($items),
                'use_bynder_cdn'   => 1
            ],
            $storeId
        );
    }

    /**
     * Update documents (bynder_document attribute)
     *
     * @param string $img_json
     * @param string $product_sku_key
     * @param array $mg_img_role_option
     * @param string $img_alt_text
     * @param array $bynder_media_ids
     * @param string|null $select_attribute
     * @return bool
     */
    public function getUpdateDoc(
        $img_json,
        $product_sku_key,
        $mg_img_role_option,
        $img_alt_text,
        $bynder_media_ids,
        $select_attribute = null
    ) {
        try {
            $storeId         = $this->storeManagerInterface->getStore()->getId();
            $_product        = $this->_productRepository->get($product_sku_key);
            $product_ids     = $_product->getId();
            $doc_values      = $_product->getBynderDocument();
            $bynder_media_id = $bynder_media_ids[$product_sku_key] ?? [];

            $item_old_value = [];
            if (!empty($doc_values)) {
                $decoded = json_decode($doc_values, true);
                if (is_array($decoded)) {
                    $item_old_value = $decoded;
                }
            }

            $existing_ids = [];
            foreach ($item_old_value as $doc) {
                if (($doc['item_type'] ?? '') === 'DOCUMENT' && isset($doc['bynder_md_id'])) {
                    $existing_ids[] = $doc['bynder_md_id'];
                }
            }

            $doc_detail = [];
            foreach (explode("\n", (string)$img_json) as $vv => $doc_value) {
                if (trim($doc_value) === '') {
                    continue;
                }
                $doc_parts = explode('@@', $doc_value);
                $media_id  = $bynder_media_id[$vv] ?? '';

                if ($media_id !== '' && in_array($media_id, $existing_ids)) {
                    continue; // already on the product
                }

                $doc_detail[] = [
                    'item_url'     => $doc_parts[0],
                    'item_type'    => 'DOCUMENT',
                    'doc_name'     => $doc_parts[1] ?? '',
                    'bynder_md_id' => $media_id,
                ];
            }

            // One log row per SKU with all document URLs
            $this->logItemsForSku($product_sku_key, $doc_detail, '2');

            $this->productAction->updateAttributes(
                [$product_ids],
                ['bynder_document' => json_encode(array_merge($item_old_value, $doc_detail))],
                $storeId
            );
        } catch (\Exception $e) {
            $this->insertLog((string)$product_sku_key, $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Update images / videos (bynder_multi_img attribute)
     *
     * @param string $img_json
     * @param string $product_sku_key
     * @param array $mg_img_role_option
     * @param string $img_alt_text
     * @param array $bynder_media_ids
     * @param string|null $select_attribute
     * @return bool
     */
    public function getUpdateImage(
        $img_json,
        $product_sku_key,
        $mg_img_role_option,
        $img_alt_text,
        $bynder_media_ids,
        $select_attribute = null
    ) {
        $select_attribute = $this->resolveSelectAttribute($select_attribute);

        $image_detail      = [];
        $video_detail      = [];
        $diff_image_detail = [];

        try {
            $storeId         = $this->storeManagerInterface->getStore()->getId();
            $_product        = $this->_productRepository->get($product_sku_key);
            $product_ids     = $_product->getId();
            $image_value     = $_product->getBynderMultiImg();
            $bynder_media_id = $bynder_media_ids[$product_sku_key] ?? [];

            $requestPlpOverImg = $this->getRequest()->getParam('plp_over_img_value');
            $requestPdpImg     = $this->getRequest()->getParam('pdp_img_value');
            $plp_over_img = is_array($requestPlpOverImg)
                ? $requestPlpOverImg
                : explode("\n", (string)$requestPlpOverImg);
            $pdp_img = is_array($requestPdpImg)
                ? $requestPdpImg
                : explode("\n", (string)$requestPdpImg);

            $new_image_array   = explode("\n", (string)$img_json);
            $new_alttext_array = explode("\n", (string)$img_alt_text);
            $role_options      = is_array($mg_img_role_option) ? $mg_img_role_option : [];

            $old_items = [];
            if (!empty($image_value)) {
                $decoded = json_decode($image_value, true);
                if (is_array($decoded)) {
                    $old_items = $decoded;
                }
            }

            if ($select_attribute === 'image') {
                $all_item_url = array_column($old_items, 'item_url');

                foreach ($new_image_array as $vv => $new_image_value) {
                    if (trim($new_image_value) === '' || $new_image_value === 'no image') {
                        continue;
                    }
                    if (strpos($new_image_value, '@@') !== false) {
                        continue; // video line
                    }

                    $item_url      = explode('?', $new_image_value);
                    $alt_text      = $this->getAltTextValue($new_alttext_array, $vv);
                    $curt_img_role = $this->sanitizeImageRoles($role_options[$vv] ?? []);
                    $media_id      = $bynder_media_id[$vv] ?? '';

                    $image_detail[] = [
                        'item_url'     => $new_image_value,
                        'alt_text'     => $alt_text,
                        'image_role'   => $curt_img_role,
                        'item_type'    => 'IMAGE',
                        'thum_url'     => $item_url[0],
                        'bynder_md_id' => $media_id,
                        'is_import'    => 0
                    ];
                    $image_detail = $this->removeRolesFromOthers($image_detail, $curt_img_role);

                    if (!empty($old_items) && !in_array($item_url[0], $all_item_url)) {
                        $diff_image_detail[] = [
                            'item_url'     => $new_image_value,
                            'alt_text'     => $alt_text,
                            'image_role'   => $curt_img_role,
                            'item_type'    => 'IMAGE',
                            'thum_url'     => $new_image_value,
                            'bynder_md_id' => $media_id,
                            'is_import'    => 0
                        ];
                        $old_items = $this->removeRolesFromOthers($old_items, $curt_img_role, false);
                        $diff_image_detail = $this->removeRolesFromOthers($diff_image_detail, $curt_img_role);
                    }
                }

                if (!empty($diff_image_detail)) {
                    $this->getInsertMedaiDataTable(
                        $product_sku_key,
                        array_column($diff_image_detail, 'bynder_md_id'),
                        $product_ids,
                        $storeId
                    );
                }

                // Keep the existing videos
                $old_video_detail = [];
                foreach ($old_items as $img) {
                    if (($img['item_type'] ?? '') === 'VIDEO') {
                        $old_video_detail[] = [
                            'item_url'     => $img['item_url'],
                            'image_role'   => null,
                            'item_type'    => 'VIDEO',
                            'thum_url'     => $img['thum_url'] ?? '',
                            'bynder_md_id' => $img['bynder_md_id'] ?? ''
                        ];
                    }
                }

                $final_items = array_merge($image_detail, $old_video_detail);

                $this->logItemsForSku($product_sku_key, $image_detail, '1');
                $this->saveMultiImg($product_sku_key, $product_ids, $storeId, $final_items, !empty($old_items));

            } elseif ($select_attribute === 'video') {
                $old_item_url = array_column($old_items, 'item_url');

                foreach ($new_image_array as $vv => $video_value) {
                    if (strpos($video_value, '@@') === false) {
                        continue;
                    }
                    $parts = explode('@@', $video_value);
                    if (in_array($parts[0], $old_item_url)) {
                        continue;
                    }
                    $video_detail[] = [
                        'item_url'     => $parts[0],
                        'image_role'   => null,
                        'item_type'    => 'VIDEO',
                        'thum_url'     => $parts[1] ?? '',
                        'bynder_md_id' => $bynder_media_id[$vv] ?? ''
                    ];
                }

                $final_items = array_merge($old_items, $video_detail);

                $this->logItemsForSku($product_sku_key, $video_detail, '3');
                $this->saveMultiImg($product_sku_key, $product_ids, $storeId, $final_items, !empty($old_items));

            } elseif ($select_attribute === 'all_attribute') {
                foreach ($new_image_array as $vv => $line) {
                    if (trim($line) === '' || $line === 'no image') {
                        continue;
                    }

                    if (strpos($line, '@@') === false) {
                        $curt_img_role = $this->sanitizeImageRoles($role_options[$vv] ?? []);
                        $image_detail[] = [
                            'item_url'           => $line,
                            'plp_over_img_value' => $plp_over_img[$vv] ?? '',
                            'pdp_img_value'      => $pdp_img[$vv] ?? '',
                            'alt_text'           => $this->getAltTextValue($new_alttext_array, $vv),
                            'image_role'         => $curt_img_role,
                            'item_type'          => 'IMAGE',
                            'thum_url'           => $line,
                            'bynder_md_id'       => $bynder_media_id[$vv] ?? '',
                            'is_import'          => 0
                        ];
                        $image_detail = $this->removeRolesFromOthers($image_detail, $curt_img_role);
                    } else {
                        $parts = explode('@@', $line);
                        $video_detail[] = [
                            'item_url'     => $parts[0],
                            'image_role'   => null,
                            'item_type'    => 'VIDEO',
                            'thum_url'     => $parts[1] ?? '',
                            'bynder_md_id' => $bynder_media_id[$vv] ?? ''
                        ];
                    }
                }

                // One log row per SKU: all images together, all videos together
                $this->logItemsForSku($product_sku_key, $image_detail, '1');
                $this->logItemsForSku($product_sku_key, $video_detail, '3');

                $final_items = array_merge($image_detail, $video_detail);
                $this->saveMultiImg($product_sku_key, $product_ids, $storeId, $final_items, false);

            } else {
                $this->insertLog((string)$product_sku_key, 'Unknown select attribute: ' . $select_attribute);
                return false;
            }
        } catch (\Exception $e) {
            $this->insertLog((string)$product_sku_key, $e->getMessage());
            return false;
        }

        return true;
    }
}