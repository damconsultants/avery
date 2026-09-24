<?php

namespace DamConsultants\Avery\Cron;

use Exception;
use \Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\Product\Action;
use DamConsultants\Avery\Model\BynderFactory;
use DamConsultants\Avery\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;
use DamConsultants\Avery\Model\ResourceModel\Collection\BynderMediaTableCollectionFactory;

class AutoAddFromMagento
{
    /**
     * Roles assigned to the FIRST image when Bynder returns no role for any image.
     * Change these labels to match the exact options of your magento_role attribute.
     */
    private const DEFAULT_IMAGE_ROLES = ["Base", "Small", "Thumbnail", "Swatch"];

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var $bynderMediaTable
     */
    protected $bynderMediaTable;
    /**
     * @var $bynderMediaTableCollectionFactory
     */
    protected $bynderMediaTableCollectionFactory;
    /**
     * @var $_productRepository
     */
    protected $_productRepository;
    /**
     * @var $datahelper
     */
    protected $datahelper;
    /**
     * @var $action
     */
    protected $action;
    /**
     * @var $_bynderAutoReplaceData
     */
    protected $_bynderAutoReplaceData;
    /**
     * @var $metaPropertyCollectionFactory
     */
    protected $metaPropertyCollectionFactory;
    /**
     * @var $storeManagerInterface
     */
    protected $storeManagerInterface;
    /**
     * @var $configWriter
     */
    protected $configWriter;
    /**
     * @var $resouce
     */
    protected $resouce;
    /**
     * @var $collectionFactory
     */
    protected $collectionFactory;
    /**
     * @var $bynder
     */
    protected $bynder;
    /**
     * @var $_resource
     */
    protected $_resource;

    /**
     * Featch Null Data To Magento
     * @param LoggerInterface $this->logger
     * @param ProductRepository $productRepository
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManagerInterface
     * @param \DamConsultants\Avery\Helper\Data $DataHelper
     * @param \DamConsultants\Avery\Model\BynderAutoReplaceDataFactory $bynderAutoReplaceData
     * @param DamConsultants\Avery\Model\BynderMediaTableFactory $bynderMediaTable
     * @param BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory
     * @param Action $action
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param BynderFactory $bynder
     * @param \Magento\Framework\App\ResourceConnection $resource
     */
    public function __construct(
        LoggerInterface $logger,
        ProductRepository $productRepository,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Avery\Helper\Data $DataHelper,
        \DamConsultants\Avery\Model\BynderAutoReplaceDataFactory $bynderAutoReplaceData,
        \DamConsultants\Avery\Model\BynderMediaTableFactory $bynderMediaTable,
        BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory,
        Action $action,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        BynderFactory $bynder,
        \Magento\Framework\App\ResourceConnection $resource
    ) {
        $this->logger = $logger;
        $this->_productRepository = $productRepository;
        $this->collectionFactory = $collectionFactory;
        $this->datahelper = $DataHelper;
        $this->action = $action;
        $this->_bynderAutoReplaceData = $bynderAutoReplaceData;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->bynderMediaTable = $bynderMediaTable;
        $this->bynderMediaTableCollectionFactory = $bynderMediaTableCollectionFactory;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->bynder = $bynder;
        $this->_resource = $resource;
    }
    /**
     * Execute
     *
     * @return boolean
     */
    public function execute()
    {
        $this->logger->info("Auto Add Image Value");
        $enable = $this->datahelper->getAutoCronEnable();
        if (!$enable) {
            return false;
        }
        $product_collection = $this->collectionFactory->create();
        $product_sku_limit = (int)$this->datahelper->getAutoProductSkuLimitConfig();
        if (!empty($product_sku_limit)) {
            $product_collection->getSelect()->limit($product_sku_limit);
        } else {
            $product_collection->getSelect()->limit(50);
        }
        $product_collection->addAttributeToSelect('*')
            ->addAttributeToFilter(
                [
                    ['attribute' => 'bynder_multi_img', 'notnull' => true]
                ]
            )
            ->addAttributeToFilter(
                [
                    ['attribute' => 'bynder_auto_replace', 'null' => true]
                ]
            )
            ->addAttributeToFilter('type_id', ['neq' => "configurable"])
            ->load();
        $property_id = null;
        $collection = $this->metaPropertyCollectionFactory->create()->getData();
        $meta_properties = $this->getMetaPropertiesCollection($collection);

        $collection_value = $meta_properties['collection_data_value'];
        $collection_slug_val = $meta_properties['collection_data_slug_val'];

        $productSku_array = [];
        foreach ($product_collection->getData() as $product) {
            $productSku_array[] = $product['sku'];
        }
        $this->logger->info("Sku => ". json_encode($productSku_array, true));
        if (count($productSku_array) > 0) {
            foreach ($productSku_array as $sku) {
                if ($sku != "") {
                    $bd_sku = trim(preg_replace('/[^A-Za-z0-9-]/', '_', $sku));
                    $get_data = $this->datahelper->getImageSyncWithProperties($bd_sku, $property_id, $collection_value);
                    if (!empty($get_data) && $this->getIsJSON($get_data)) {
                        $respon_array = json_decode($get_data, true);
                        if ($respon_array['status'] == 1) {
                            $convert_array = json_decode($respon_array['data'], true);
                            if ($convert_array['status'] == 1) {
                                $current_sku = $sku;
                                try {
                                    $this->getDataItem($convert_array, $collection_slug_val, $current_sku);
                                } catch (Exception $e) {
                                    $insert_data = [
                                        "sku" => $sku,
                                        "message" => $e->getMessage(),
                                        'media_id' => "",
                                        "data_type" => ""
                                    ];
                                    $this->getInsertDataTable($insert_data);
                                }
                                
                            } else {
                                $insert_data = [
                                    "sku" => $sku,
                                    "message" => $convert_array['data'],
                                    'media_id' => "",
                                    "data_type" => ""
                                ];
                                $this->getInsertDataTable($insert_data);
                            }
                        } else {
                            $insert_data = [
                                "sku" => $sku,
                                "message" => 'Please Select The Metaproperty First.....',
                                'media_id' => "",
                                "data_type" => ""
                            ];
                            $this->getInsertDataTable($insert_data);
                        }
                    } else {
                        $insert_data = [
                            "sku" => $sku,
                            "message" => "Something problem in DAM side please contact to developer.",
                            'media_id' => "",
                            "data_type" => ""
                        ];
                        $this->getInsertDataTable($insert_data);
                    }
                }
            }
        } else {
            $product_collection = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter(
                [
                    ['attribute' => 'bynder_auto_replace', 'notnull' => true]
                ]
            )
            ->load();
            $id = [];
            foreach ($product_collection as $product) {
                $id[] = $product->getId();
            }
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $this->action->updateAttributes(
                $id,
                ['bynder_auto_replace' => ""],
                $storeId
            );
        }
        $this->logger->info("AveryAuto Replace Attribute Null");
        return true;
    }

    /**
     * Get Meta Properties Collection
     *
     * @param array $collection
     * @return array $response_array
     */
    public function getMetaPropertiesCollection($collection)
    {
        $this->logger->info("getMetaPropertiesCollection");
        $collection_data_value = [];
        $collection_data_slug_val = [];
        if (count($collection) >= 1) {
            foreach ($collection as $key => $collection_value) {
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
                    'bynder_property_slug' => $collection_value['system_slug'],
                ];
            }
        }
        $response_array = [
            "collection_data_value" => $collection_data_value,
            "collection_data_slug_val" => $collection_data_slug_val
        ];
        return $response_array;
    }

    /**
     * Is int
     *
     * @return $this
     */
    public function getMyStoreId()
    {
        $storeId = $this->storeManagerInterface->getStore()->getId();
        return $storeId;
    }

    /**
     * Is Json
     *
     * @param string $string
     * @return $this
     */
    public function getIsJSON($string)
    {
        return ((json_decode($string)) === null) ? false : true;
    }
    /**
     * Is Json
     *
     * @param array $insert_data
     * @return $this
     */
    public function getInsertDataTable($insert_data)
    {
        $model = $this->_bynderAutoReplaceData->create();
        $data_image_data = [
            'sku' => $insert_data['sku'],
            'bynder_data' =>$insert_data['message'],
            'media_id' => $insert_data['media_id'],
            'bynder_data_type' => $insert_data['data_type']
        ];
        
        $model->setData($data_image_data);
        $model->save();
    }
    /**
     * Is Json
     *
     * @param string $sku
     * @param array $m_id
     * @param string $storeId
     * @param string $product_ids
     * @return $this
     */
    public function getInsertMedaiDataTable($sku, $m_id, $storeId, $product_ids)
    {
        $model = $this->bynderMediaTable->create();
        $modelcollection = $this->bynderMediaTableCollectionFactory->create();
        $modelcollection->addFieldToFilter('sku', ['eq' => [$sku]])->load();
        $table_m_id = [];
        if (!empty($modelcollection)) {
            foreach ($modelcollection as $mdata) {
                $table_m_id[] = $mdata['media_id'];
            }
        }
        $media_diff = array_diff($m_id, $table_m_id);
        foreach ($media_diff as $new_data) {
            $data_image_data = [
                'sku' => $sku,
                'media_id' => trim($new_data),
                'status' => "1",
            ];
            $model->setData($data_image_data);
            $model->save();
        }
        $updated_values = [
            'bynder_delete_cron' => 1
        ];
        $this->action->updateAttributes(
            [$product_ids],
            $updated_values,
            $storeId
        );
    }
    /**
     * Is Json
     *
     * @param string $sku
     * @param string $media_id
     * @return $this
     */
    public function getDeleteMedaiDataTable($sku, $media_id)
    {
        $model = $this->bynderMediaTableCollectionFactory->create()->addFieldToFilter('sku', ['eq' => [$sku]])->load();
        foreach ($model as $mdata) {
            if ($mdata['media_id'] != $media_id) {
                $this->bynderMediaTable->create()->load($mdata['id'])->delete();

            }
        }
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
     * Clean placeholder roles and, when the API returned no role for ANY image,
     * assign the static roles to the first image of the SKU.
     *
     * @param array $image_detail
     * @return array
     */
    private function ensureDefaultImageRoles(array $image_detail): array
    {
        $hasRole = false;

        foreach ($image_detail as $key => $item) {
            if (($item['item_type'] ?? '') !== 'IMAGE') {
                continue;
            }
            $image_detail[$key]['image_role'] = $this->sanitizeImageRoles($item['image_role'] ?? []);
            if (count($image_detail[$key]['image_role']) > 0) {
                $hasRole = true;
            }
        }

        if (!$hasRole) {
            foreach ($image_detail as $key => $item) {
                if (($item['item_type'] ?? '') === 'IMAGE') {
                    $image_detail[$key]['image_role'] = self::DEFAULT_IMAGE_ROLES;
                    break;
                }
            }
        }

        return $image_detail;
    }

    /**
     * Prepare image role, alt text, and media id values from the API response.
     *
     * @param array $imageData
     * @param string|int $bynderMediaId
     * @return array
     */
    private function prepareImageMetadata(array $imageData, $bynderMediaId): array
    {
        $roles = [];
        $altTexts = [];
        $mediaIds = [];

        $roleOptions = $imageData['magento_role_options'] ?? [];
        $roleDetails = $imageData['magento_role_options_details'] ?? [];

        if (!is_array($roleOptions)) {
            $roleOptions = [];
        }

        if (!is_array($roleDetails)) {
            $roleDetails = [];
        }

        if (count($roleOptions) > 0) {
            foreach ($roleOptions as $index => $roleOption) {
                $normalizedRole = $this->normalizeMagentoRole($roleOption);
                if ($normalizedRole === '' && isset($roleDetails[$index]['label'])) {
                    $normalizedRole = trim((string)$roleDetails[$index]['label']);
                }
                if ($normalizedRole === '' && isset($roleDetails[$index]['slug'])) {
                    $normalizedRole = trim((string)$roleDetails[$index]['slug']);
                }
                $roles[] = $normalizedRole !== '' ? $normalizedRole : '###';

                $altTextValue = $imageData['img_alt_text'] ?? '';
                if (is_array($altTextValue)) {
                    $altTextValue = implode(' ', $altTextValue);
                }
                $altTextValue = trim((string)$altTextValue);
                $altTexts[] = $altTextValue !== '' ? $altTextValue . "\n" : "###\n";
                $mediaIds[] = $bynderMediaId;
            }
        } elseif (count($roleDetails) > 0) {
            foreach ($roleDetails as $detail) {
                $normalizedRole = trim((string)($detail['label'] ?? ''));
                if ($normalizedRole === '') {
                    $normalizedRole = trim((string)($detail['slug'] ?? ''));
                }
                $roles[] = $normalizedRole !== '' ? $normalizedRole : '###';

                $altTextValue = $imageData['img_alt_text'] ?? '';
                if (is_array($altTextValue)) {
                    $altTextValue = implode(' ', $altTextValue);
                }
                $altTextValue = trim((string)$altTextValue);
                $altTexts[] = $altTextValue !== '' ? $altTextValue . "\n" : "###\n";
                $mediaIds[] = $bynderMediaId;
            }
        }

        if (empty($this->sanitizeImageRoles($roles))) {
            // No usable role came back. Keep the placeholder here; the static roles
            // are applied later by ensureDefaultImageRoles() on the first image only.
            $roles = ['###'];
            $altTexts = [];
            $mediaIds = [];

            $altTextValue = $imageData['img_alt_text'] ?? '';
            if (is_array($altTextValue)) {
                $altTextValue = implode(' ', $altTextValue);
            }
            $altTextValue = trim((string)$altTextValue);

            // One alt text / media id per ITEM, never per role.
            $altTexts[] = $altTextValue !== '' ? $altTextValue . "\n" : "###\n";
            $mediaIds[] = $bynderMediaId;
        }

        return [
            'roles' => $roles,
            'alt_text' => $altTexts,
            'media_ids' => $mediaIds,
        ];
    }
    /**
     * Get Data Item
     *
     * @param array $convert_array
     * @param array $collection_data_slug_val
     * @param string $current_sku
     */
    public function getDataItem($convert_array, $collection_data_slug_val, $current_sku)
    {
        $data_arr = [];
        $data_val_arr = [];
        if ($convert_array['status'] == 1) {
			
            foreach ($convert_array['data'] as $data_value) {
                $bynder_media_id = $data_value['id'];
                $image_data = $data_value['thumbnails'] ?? [];
                $sku_slug_name = "property_" . $collection_data_slug_val['sku']['bynder_property_slug'];
                $data_sku[0] = $current_sku;
                $new_magento_role_list = [];
                $new_bynder_alt_text = [];
                $new_bynder_mediaid_text = [];
                $new_image_role = [];

                $imageMetadata = $this->prepareImageMetadata($image_data, $bynder_media_id);
                $new_magento_role_list = $imageMetadata['roles'];
                $new_bynder_alt_text = $imageMetadata['alt_text'];
                $new_bynder_mediaid_text = $imageMetadata['media_ids'];

                // image order metadata is ignored in this cron flow

				$new_bynder_mediaid_text = array_unique($new_bynder_mediaid_text);
				$new_bynder_alt_text = array_unique($new_bynder_alt_text);
                if ($data_value['type'] == "image") {
                    $image_link = $image_data['Hi_Res_JPG'] ?? "";
                    array_push($data_arr, $data_sku[0]);
                    $data_p = [
                        "sku" => $data_sku[0],
                        "url" => [$image_link."\n"], /* chagne by kuldip ladola for testing perpose */
                        'magento_image_role' => $new_magento_role_list,
                        'image_alt_text' => $new_bynder_alt_text,
                        'bynder_media_id_new' => $new_bynder_mediaid_text,
                        "type" => "image"
                    ];
                    array_push($data_val_arr, $data_p);
                } else {
                    if ($data_value['type'] == 'video') {
                        $video_link = ($data_value["videoPreviewURLs"][0] ?? "") . '@@' . ($image_data["webimage"] ?? "");
                        array_push($data_arr, $data_sku[0]);
                        $data_p = [
                            "sku" => $data_sku[0],
                            "url" => [$video_link. "\n"],
                            'magento_image_role' => $new_image_role,
                            'image_alt_text' => $new_bynder_alt_text,
                            'bynder_media_id_new' => $new_bynder_mediaid_text,
                            "type" => "video"
                        ];
                        array_push($data_val_arr, $data_p);

                    } else {
                        $doc_name = $data_value["name"];
                        $doc_name_with_space = preg_replace("/[^a-zA-Z]+/", "-", $doc_name);
                        $doc_link = "";
						if (!empty($data_value['derivatives']) && is_array($data_value['derivatives'])) {
							foreach ($data_value['derivatives'] as $derivative) {
								if (isset($derivative['public_url']) && !empty($derivative['public_url'])) {
									$doc_link = $derivative['public_url'] . '@@' . $doc_name . "\n";
									break; // take the first available public_url
								}
							}
						}
                        if (!empty($doc_link)) {
							array_push($data_arr, $data_sku[0]);
                            $data_p = [
                                "sku" => $data_sku[0],
                                "url" => [$doc_link],
                                'magento_image_role' => $new_image_role,
                                'image_alt_text' => $new_bynder_alt_text,
                                'bynder_media_id_new' => $new_bynder_mediaid_text,
                                "type" => "document"
                            ];
							array_push($data_val_arr, $data_p);
						}
                    }

                }
            }
        }
        if (count($data_arr) > 0) {
            $this->getProcessItem($data_arr, $data_val_arr);
        }
    }
    /**
     * Get Process Item
     *
     * @param array $data_arr
     * @param array $data_val_arr
     */
    public function getProcessItem($data_arr, $data_val_arr)
    {
        $this->logger->info("getProcessItem");

        $image_value_details_role = [];
        $temp_arr = [];
		$types = [];
		$image_alt_text = [];
		$byn_md_id_new = [];
        foreach ($data_arr as $key => $skus) {
            $temp_arr[$skus][] =  implode("", $data_val_arr[$key]["url"]);
            $image_value_details_role[$skus][] = $data_val_arr[$key]["magento_image_role"];
            $image_alt_text[$skus][] = implode("", $data_val_arr[$key]["image_alt_text"]);
            $byn_md_id_new[$skus][] = implode("", $data_val_arr[$key]["bynder_media_id_new"]);
            $types[] = $data_val_arr[$key]['type'];
        }
		$types = array_unique($types);
        foreach ($temp_arr as $product_sku_key => $image_value) {
            $img_json = implode("", $image_value);
            $mg_role = $image_value_details_role[$product_sku_key];
            $image_alt_text_value = implode("", $image_alt_text[$product_sku_key]);
            $this->getUpdateImage(
                $img_json,
                $product_sku_key,
                $mg_role,
                $image_alt_text_value,
                $byn_md_id_new,
                $types
            );
        }
    }

    /**
     * Upate Item
     *
     * @return $this
     * @param string $img_json
     * @param string $product_sku_key
     * @param string $mg_img_role_option
     * @param string $img_alt_text
     * @param string $bynder_media_id
     * @param array $type
     */
    public function getUpdateImage($img_json, $product_sku_key, $mg_img_role_option, $img_alt_text, $bynder_media_id, $type)
    {
        $this->logger->info("getUpdateImage");
        $diff_image_detail = [];
        $new_image_detail = [];
        $diff_video_detail = [];
        $new_video_detail = [];
        $image = [];
        $video = [];
        $select_attribute = "image";
        $image_detail = [];
        $video_detail = [];
        try {
            
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $_product = $this->_productRepository->get($product_sku_key);
            $product_ids = $_product->getId();
            $image_value = $_product->getBynderMultiImg();
            $doc_values = $_product->getBynderDocument();
            $auto_replace = $_product->getBynderAutoReplace();
            $bynder_media_ids = $bynder_media_id[$product_sku_key];
            if (in_array("image", $type) || in_array("video", $type)) {
                if (!empty($image_value) && $auto_replace == null) {
                    $new_image_array = explode("\n", $img_json);
                    
                    $new_alttext_array = explode("\n", $img_alt_text);
                    $new_magento_role_option_array = $mg_img_role_option;
                    $all_item_url = [];
                    $all_video_url = [];
                    $item_old_value = json_decode($image_value, true);
                    if (count($item_old_value) > 0) {
                        foreach ($item_old_value as $img) {
                            if ($img['item_type'] == 'IMAGE') {
                                $all_item_url[] = $img['item_url'];
                            } else {
                                $all_video_url[] = $img['item_url'];
                            }
                        }
                    }
                    $this->logger->info("all_item_url => ". json_encode($all_item_url));
                    foreach ($new_image_array as $vv => $new_image_value) {
                        if (trim($new_image_value) != "" && $new_image_value != "no image") {
                            $item_url = explode("?", $new_image_value);
                            $media_image_explode = explode("/", $item_url[0]);
                            $img_altText_val = "";
                            if (isset($new_alttext_array[$vv])) {
                                if ($new_alttext_array[$vv] != "###" && strlen(trim($new_alttext_array[$vv])) > 0) {
                                    $img_altText_val = $new_alttext_array[$vv];
                                }
                            }
                            $curt_img_role = $this->sanitizeImageRoles(
                                $new_magento_role_option_array[$vv] ?? []
                            );
                            $find_video = strpos($new_image_value, "@@");
							$find_doc = strpos($new_image_value, "??");
                            if (!$find_video && !$find_doc) {
                                $this->logger->info("image_detail => ". $new_image_value);
                                $image_detail[] = [
                                    "item_url" => $new_image_value,
                                    "alt_text" => $img_altText_val,
                                    "image_role" => $curt_img_role,
                                    "item_type" => 'IMAGE',
                                    "thum_url" => $item_url[0],
                                    "bynder_md_id" => $bynder_media_ids[$vv],
                                    "is_import" => 0
                                ];
                                $total_new_values = count($image_detail);
                                if ($total_new_values > 1) {
                                    foreach ($image_detail as $nn => $n_img) {
                                        if ($n_img['item_type'] == "IMAGE" && $nn != ($total_new_values - 1)) {
                                            $new_mg_role_array = $this->sanitizeImageRoles(
                                                $new_magento_role_option_array[$vv] ?? []
                                            );
                                            if (is_array($n_img["image_role"])
                                                && count($n_img["image_role"]) > 0
                                                && count($new_mg_role_array) > 0
                                            ) {
                                                $result_val=array_diff($n_img["image_role"], $new_mg_role_array);
                                                $image_detail[$nn]["image_role"] = $result_val;
                                            }
                                        }
                                    }
                                }
                                if (!in_array($item_url[0], $all_item_url)) {
                                    $this->logger->info("diff_image_detail => ". $new_image_value);
                                    $diff_image_detail[] = [
                                        "item_url" => $new_image_value,
                                        "alt_text" => $img_altText_val,
                                        "image_role" => $curt_img_role,
                                        "item_type" => 'IMAGE',
                                        "thum_url" => $new_image_value,
                                        "bynder_md_id" => $bynder_media_ids[$vv],
                                        "is_import" => 0
                                    ];
                                    $data_image_data = [
                                        'sku' => $product_sku_key,
                                        'message' => $new_image_value,
                                        'media_id' => $bynder_media_ids[$vv],
                                        'data_type' => '1'
                                    ];
                                    $this->getInsertDataTable($data_image_data);
                                    if (count($item_old_value) > 0) {
                                        foreach ($item_old_value as $kv => $img) {
                                            if ($img['item_type'] == "IMAGE") {
                                                /* here changes by me but not tested */
                                                $new_mg_role_array = $this->sanitizeImageRoles(
                                                    $new_magento_role_option_array[$vv] ?? []
                                                );
                                                if (isset($img["image_role"])
                                                    && is_array($img["image_role"])
                                                    && count($img["image_role"]) > 0
                                                    && count($new_mg_role_array) > 0
                                                ) {
                                                    $result_val=array_diff($img["image_role"], $new_mg_role_array);
                                                    $item_old_value[$kv]["image_role"] = $result_val;
                                                }
                                            }
                                        }
                                    }
                                    $total_new_value = count($diff_image_detail);
                                    if ($total_new_value > 1) {
                                        foreach ($diff_image_detail as $nn => $n_img) {
                                            if ($n_img['item_type'] == "IMAGE" && $nn != ($total_new_value - 1)) {
                                                $new_mg_role_array = $this->sanitizeImageRoles(
                                                    $new_magento_role_option_array[$vv] ?? []
                                                );
                                                if (is_array($n_img["image_role"])
                                                    && count($n_img["image_role"]) > 0
                                                    && count($new_mg_role_array) > 0
                                                ) {
                                                    $result_val=array_diff($n_img["image_role"], $new_mg_role_array);
                                                    $diff_image_detail[$nn]["image_role"] = $result_val;
                                                }
                                            }
                                        }
                                    }
                                }
                            } elseif($find_video) {
                                $item_url = explode("@@", $new_image_value);
                                $thum_url = explode("@@", $new_image_value);
                                $media_video_explode = explode("/", $item_url[0]);
                                $this->logger->info("video_detail => ". $item_url[0]);
                                $video_detail[] = [
                                    "item_url" => $item_url[0],
                                    "image_role" => null,
                                    "item_type" => 'VIDEO',
                                    "thum_url" => $thum_url[1],
                                    "bynder_md_id" => $bynder_media_ids[$vv]
                                ];
                                if (!in_array($item_url[0], $all_video_url)) {
                                    $this->logger->info("diff_video_detail => ". $item_url[0]);
                                    $diff_video_detail[] = [
                                        "item_url" => $item_url[0],
                                        "image_role" => null,
                                        "item_type" => 'VIDEO',
                                        "thum_url" => $thum_url[1],
                                        "bynder_md_id" => $bynder_media_ids[$vv]
                                    ];
                                    $data_image_data = [
                                        'sku' => $product_sku_key,
                                        'message' => $item_url[0],
                                        'media_id' => $bynder_media_ids[$vv],
                                        'data_type' => '3'
                                    ];
                                    $this->getInsertDataTable($data_image_data);
                                }
                            }
                        }
                    }
                    // No role from API -> static roles on the first image, and strip "###".
                    $image_detail = $this->ensureDefaultImageRoles($image_detail);
                    $diff_image_detail = $this->ensureDefaultImageRoles($diff_image_detail);
                    $d_img_roll = "";
                    $d_media_id = [];
                    if (count($diff_image_detail) > 0) {
                        foreach ($diff_image_detail as $d_img) {
                            $d_img_roll = $d_img['image_role'];
                            $d_media_id[] =  $d_img['bynder_md_id'];
                        }
                        $this->getInsertMedaiDataTable($product_sku_key, $d_media_id, $storeId, $product_ids);
                    }
                    $dv_media_id = [];
                    if (count($diff_video_detail) > 0) {
                        foreach ($diff_image_detail as $d_video) {
                            $dv_media_id[] =  $d_video['bynder_md_id'];
                        }
                        $this->getInsertMedaiDataTable($product_sku_key, $dv_media_id, $storeId, $product_ids);
                    }
                    $i_img_roll = "";
                    $image_link = "";
                    if (count($image_detail) > 0) {
                        foreach ($image_detail as $img) {
                            $image[] = $img['item_url'];
                            if (!empty($img['image_role'])) {
                                $image_link = $img['item_url'];
                                $i_img_roll = $img['image_role'];
                            }
                        }
                    }
                    if (count($video_detail) > 0) {
                        foreach ($video_detail as $video) {
                            $video[] = $video['item_url'];
                        }
                    }
                    foreach ($item_old_value as $key1 => $img) {
                        if ($img['item_type'] == 'IMAGE') {
                            if (in_array($img['item_url'], $image)) {
                                $item_key = array_search($img['item_url'], array_column($image_detail, "item_url"));
                                if (isset($d_img_roll)) {
                                    $roll = $image_detail[$item_key]['image_role'];
                                } else {
                                    $roll = $img['image_role'];
                                }
                                $new_image_detail[] = [
                                    "item_url" => $img['item_url'],
                                    "alt_text" => $image_detail[$item_key]['alt_text'],
                                    "image_role" => $roll,
                                    "item_type" => $img['item_type'],
                                    "thum_url" => $img['thum_url'],
                                    "bynder_md_id" => $img['bynder_md_id'],
                                    "is_import" => $img['is_import']
                                ];
                            }
                            $total_new_value = count($new_image_detail);
                            if ($total_new_value > 1) {
                                foreach ($new_image_detail as $nn => $n_img) {
                                    if ($n_img['item_type'] == "IMAGE" && $nn != ($total_new_value - 1)) {
                                        $new_mg_role_array = $this->sanitizeImageRoles(
                                            $new_magento_role_option_array[$item_key] ?? []
                                        );
                                        if (is_array($n_img["image_role"])
                                            && count($n_img["image_role"]) > 0
                                            && count($new_mg_role_array) > 0
                                        ) {
                                            $result_val=array_diff($n_img["image_role"], $new_mg_role_array);
                                            $new_image_detail[$nn]["image_role"] = $result_val;
                                        }
                                    }
                                }
                            }
                        } else {
                            if (count($video) > 0) {
                                if (in_array($img['item_url'], $video)) {
                                    $new_video_detail[] = [
                                        "item_url" => $img['item_url'],
                                        "image_role" => null,
                                        "item_type" => 'VIDEO',
                                        "thum_url" => $img['thum_url'],
                                        "bynder_md_id" => $img['bynder_md_id']
                                    ];
                                }
                            }
                        }
                    }
                    $this->logger->info("diff_image_detail => ". json_encode($diff_image_detail));
                    $this->logger->info("new_image_detail => ". json_encode($new_image_detail));
                    $merge_img_video = array_merge($new_image_detail, $new_video_detail);
                    $merge_diff_img_video = array_merge($diff_video_detail, $diff_image_detail);
                    //$array_merge = array_merge($merge_img_video, $merge_diff_img_video);
                    $array_merge = array_merge($image_detail,  $video_detail);
                    $this->logger->info("array_merge => ". json_encode($array_merge));
                    $m_id = [];
                    $types = [];
                    foreach ($array_merge as $img) {
                        $types[] = $img['item_type'];
                        $m_id[] = $img['bynder_md_id'];
                        $this->getDeleteMedaiDataTable($product_sku_key, $img['bynder_md_id']);
                    }
                    $this->getInsertMedaiDataTable($product_sku_key, $d_media_id, $storeId, $product_ids);
                    $flag = 0;
                    if (in_array("IMAGE", $types) && in_array("VIDEO", $types)) {
                        $flag = 1;
                    } elseif (in_array("IMAGE", $types)) {
                        $flag = 2;
                    } elseif (in_array("VIDEO", $types)) {
                        $flag = 3;
                    }
                    $new_value_array = json_encode($array_merge, true);
                    $updated_values = [
                        'bynder_multi_img' => $new_value_array,
                        'bynder_isMain' => $flag,
                        'bynder_auto_replace' => 1,
                        'use_bynder_cdn' => 1
                    ];
                    $this->action->updateAttributes(
                        [$product_ids],
                        $updated_values,
                        $storeId
                    );
                } 
            } 
			if (in_array("document", $type)) {
                if (!empty($doc_values)) {
                    $item_old_value = json_decode($doc_values, true);
                    if (is_array($item_old_value)) {
                        if (count($item_old_value) > 0) {
                            foreach ($item_old_value as $doc) {
                                if ($doc['item_type'] == 'DOCUMENT') {
                                    $all_item_url[] = $doc['item_url'];
                                    $b_id[] = $doc['bynder_md_id'];
                                }
                            }
                        }
                    }
                    $new_doc_array = explode("\n", $img_json);
                    $doc_detail = [];
                    foreach ($new_doc_array as $vv => $doc_value) {
						$find_doc = strpos($doc_value, "??");
						if ($find_doc) {
							if(!empty($doc_value)){
								$item_url = explode("??", $doc_value);
								$doc_name = explode("??", $doc_value);
								$media_doc_explode = explode("/", $item_url[0]);
								if(isset($doc_name[1]) && isset($bynder_media_ids[$vv])){
									$doc_detail[] = [
										"item_url" => $item_url[0],
										"item_type" => 'DOCUMENT',
										"doc_name" => $doc_name[1],
										"bynder_md_id" => $bynder_media_ids[$vv]
									];
									$data_doc_value = [
										'sku' => $product_sku_key,
										'message' => $item_url[0],
										'data_type' => '2',
										'media_id' => $bynder_media_ids[$vv],
										'lable' => 1
									];
									$this->getInsertDataTable($data_doc_value);
								}
							}
						}
                    }
                    //$array_merg = array_merge($item_old_value, $doc_detail);
                    $new_value_array = json_encode($doc_detail, true);
                    $this->action->updateAttributes(
                        [$product_ids],
                        ['bynder_document' => $new_value_array, 'bynder_auto_replace' => 1],
                        $storeId
                    );
                }  
            }
            
        } catch (Exception $e) {
            $this->logger->info("Sku => ". $product_sku_key ."error => ". $e->getMessage());
        }
    }
}