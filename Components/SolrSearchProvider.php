<?php
namespace Qf\Components;

use Qf\Kernel\Exception;

/**
 *
 * 基于solr搜索引擎的查询封装
 *
 * @version $Id: $
 */
class SolrSearchProvider extends Provider
{
    /**
     * 查询条件字段值等于该常量,标识忽略该值
     * 只做是否为空的逻辑判断
     */
    const NOT_SET_VALUE = 'NOT_SET_VALUE';

    protected $solrClient;
    /**
     * @var SolrQuery
     */
    protected $solrQuery;

    public $configFile;
    public $path;
    public $timeout;
    public $wt;
    public $login;
    public $password;
    public $secure;
    /**
     * 软提交，只更新内存中的索引，不强制刷新到硬盘索引文件,
     * 速度快，可能有数据丢失的风险，可考虑配合后台异步增量更新
     * @var bool
     */
    public $softCommit;

    protected $inited;

    public function __construct()
    {
        if (!extension_loaded('solr')) {
            throw new Exception('solr extension not installed');
        }
        $this->timeout = 30;
        $this->wt = 'json';
        $this->secure = false;
        $this->softCommit = true;
        $this->inited = false;
    }

    protected function init()
    {
        if (!$this->inited) {
            if (!$this->configFile || !is_file($this->configFile)) {
                throw new Exception('SolrSearchProvider configFile not specified or not a file');
            }
            require $this->configFile;
            $options = array(
                'secure' => $this->secure,
                'hostname' => $host,
                'port' => $port,
                'path' => $this->path,
                'wt' => $this->wt,
                'login' => $this->login,
                'password' => $this->password,
                'timeout' => $this->timeout
            );
            $this->solrClient = new \SolrClient($options);
            $this->solrQuery = new \SolrQuery();
            $this->inited = true;
        }
    }

    /**
     * 新增、修改索引文档
     *
     * @param array $fields 索引字段，必须包含uniqueKey字段
     * @return void
     */
    public function addDoc(array $fields)
    {
        $this->init();
        $doc = new SolrInputDocument();
        foreach ($fields as $field => $value) {
            $doc->addField($field, $value);
        }
        $this->solrClient->addDocument($doc);
        $this->solrClient->commit((bool)$this->softCommit);
    }

    public function addDocs(array $fields)
    {
        $this->init();

        $docs = array();
        foreach ($fields as $field) {
            $doc = new SolrInputDocument();
            foreach ($field as $key => $value) {
                $doc->addField($key, $value);
            }
            $docs[] = $doc;
        }
        $this->solrClient->addDocuments($docs);
        $this->solrClient->commit((bool)$this->softCommit);
    }

    /**
     * 根据唯一主键id，删除索引文档
     *
     * @param mixed $id 值唯一
     * @return void
     */
    public function delDoc($id)
    {
        $this->init();
        $this->solrClient->deleteById($id);
        $this->solrClient->commit((bool)$this->softCommit);
    }

    /**
     * 根据主键id,批量删除索引文档
     *
     * @param array $ids 待删除的文档主键数组
     */
    public function delDocs(array $ids)
    {
        $this->init();
        if ($this->inited) {
            $this->solrClient->deleteByIds($ids);
            $this->solrClient->commit((bool)$this->softCommit);
        }
    }

    /**
     *
     * 按照指定条件、数量、排序搜索
     * $rule = array(
     * 'limit' => array(0, 20),
     * 'order' => array('desc' => array('created'), 'asc' => array('updated')),
     * 'query' => 'solr raw query expression', // 与conds做AND操作,可用于不指定字段的
     * 字符串模糊查询,如:"*测试*",后台可以通过copyField进行包装,like条件需要指定字段
     * 'conds' => array(
     * 'eq' => array('sign' => 1),
     * 'in' => array('sex' => array(1, 2)),
     * 'scope' => array('id' => array('min' => 0, 'max' => 1000)),
     * 'like' => array('name' => 'demo')
     * ),
     * 'facet' => array(indexField0, indexField1...), // 按给出的索引字段基于当前的查询条件分类统计数量，
     *                                               // 统计字段之间是并列关系，无交叉
     * )
     *
     * @param array $rule 查询条件数组
     * @return array array(
     *   'matches' => array(),
     *   'record' => array(),
     *   'total' => 0,
     *   'total_found' => 0,
     *   'error' => '',
     *   'facet' => array() // facet分组统计结果，fieldName => array(fieldValue => count,...)
     * );
     */
    public function search(array $rule)
    {
        if ($this->inited) {
            $this->resetQuery();
        } else {
            $this->init();
        }
        $this->parseCol($rule);
        $this->parseLimit($rule);
        $this->parseOrderBy($rule);
        $query = $this->parseConds($rule);
        if (isset($rule['query'])) {
            $query .= ($query ? ' AND ' : '') . $rule['query'];
        }
        $this->parseFacet($rule);
        $this->parseStats($rule);
        //FB::log($query);
        $res = $this->rawQuery($query);
        $limit = (isset($rule['limit']) && isset($rule['limit'][1])) ? $rule['limit'][1] : 1;
        if ($res['total'] > $limit) {
            $res['total'] = $limit;
        }
        return $res;
    }

    protected function parseStats($rule)
    {
        if (isset($rule['stats']) && is_array($rule['stats']) && $rule['stats']) {
            $this->solrQuery->setStats(true);
            $this->solrQuery->addStatsField($rule['stats']['statsField']);
            $this->solrQuery->addStatsFacet($rule['stats']['facetField']);
        }
    }

    /**
     *
     * 采用solr原生查询语句查询
     *
     * @param string $solrQuery solr原生查询语句
     * @return array
     */
    protected function rawQuery($solrQuery)
    {
        $responseBody = array();
        if ($solrQuery) {
            $this->solrQuery->setQuery($solrQuery);
        }
        // 捕获异常,防止抛出异常后导致被默认捕获函数处理导致程序退出
        try {
            $responseObj = $this->solrClient->query($this->solrQuery);
            $responseBody = $responseObj->getResponse();
        } catch (Exception $e) {
            SugarError::handleError($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        }
        return $this->parseQueryResp($responseBody);
    }

    /**
     *
     * 解析查询返回值
     *
     * @param object $responseBody SolrQueryResponse
     * @return array
     */
    protected function parseQueryResp($responseBody)
    {
        $ret = array(
            'matches' => array(),
            'record' => array(),
            'total' => 0,
            'total_found' => 0,
            'error' => '',
            'stats' => '',
            'facet' => array() // facet分组统计结果，fieldName => array(fieldValue => count,...)
        );
        if (isset($responseBody['response']) && isset($responseBody['response']['numFound'])) {
            $ret['total'] = $responseBody['response']['numFound'];
            $ret['total_found'] = $ret['total'];
            foreach ($responseBody['response']['docs'] as $doc) {
                $entry = (array)$doc;
                $ret['matches'][] = $entry['id'];
                $ret['record'][$entry['id']] = $entry;
            }
            // 按facet字段分类统计
            if ($ret['total_found'] && isset($responseBody['facet_counts'])) {
                $facetFieldArray = $responseBody['facet_counts']['facet_fields'];
                foreach ($facetFieldArray as $fieldName => $fieldValueCounts) {
                    $ret['facet'][$fieldName] = array();
                    for ($i = 0, $max = count($fieldValueCounts); $i < $max; $i += 2) {
                        $ret['facet'][$fieldName][$fieldValueCounts[$i]] = $fieldValueCounts[$i + 1];
                    }
                }
            }

            if ($this->solrQuery->getStats()) {
                $ret['stats'] = $responseBody['stats'];
            }
        }

        return $ret;
    }

    protected function parseConds(array $rule)
    {
        $fq = array();
        if (isset($rule['conds']) && is_array($rule['conds'])) {
            foreach ($rule['conds'] as $operator => $defineArray) {
                switch ($operator) {
                    case 'eq':
                    case 'neq':
                    case 'in':
                    case 'nin':
                    case 'like':
                    case 'scope':
                        $method = '_parse' . ucfirst($operator);
                        if (method_exists($this, $method) && ($ret = $this->$method($defineArray))) {
                            $fq[] = $ret;
                        }
                }
            }
        }
        return join(' AND ', $fq);
    }

    protected function parseOrderBy(array $rule)
    {
        if (isset($rule['order']) && is_array($rule['order'])) {
            foreach ($rule['order'] as $order => $fields) {
                $order = strtolower($order);
                foreach ($fields as $field) {
                    $this->solrQuery->addSortField($field, ($order == 'asc' ? SolrQuery::ORDER_ASC : SolrQuery::ORDER_DESC));
                }
            }
        }
    }

    /**
     * 解析指定的facet分组统计字段，只能返回分组的数量
     *
     * @param array $rule
     * @return void
     */
    protected function parseFacet(array $rule)
    {
        if (isset($rule['facet']) && is_array($rule['facet']) && $rule['facet']) {
            $this->solrQuery->setFacet(true);
            foreach ($rule['facet'] as $facetField) {
                $this->solrQuery->addFacetField($facetField);
            }
        }
    }

    /**
     * 指定需要返回的字段
     *
     * @param array $rule
     * @return void
     */
    protected function parseCol(array $rule)
    {
        if (isset($rule['cols']) && is_array($rule['cols'])) {
            foreach ($rule['cols'] as $col) {
                $this->solrQuery->addField($col);
            }
        } else {
            // 为减少不必要的资源消耗,未指定返回的字段时,默认只返回主键id
            $this->solrQuery->addField('id');
        }
    }

    protected function parseLike(array $defineArray)
    {
        $tmpArray = array();
        foreach ($defineArray as $field => $define) {
            $mode = 0;
            if (is_array($define)) {
                $mode = isset($define['mode']) ? $define['mode'] : $mode;
                $kw = $define['kw'];
            } else {
                $kw = $define;
            }
            $kw = $this->escape($kw);
            $prefix = $mode == 2 ? '*' : ($mode == 0 ? '*' : '');
            $suffix = $mode == 1 ? '*' : ($mode == 0 ? '*' : '');
            $tmpArray[] = "$field:$suffix$kw$suffix";
        }
        return $tmpArray ? '(' . join(' OR ', $tmpArray) . ')' : '';
    }

    protected function parseEq(array $defineArray)
    {
        $tmpArray = array();
        foreach ($defineArray as $field => $value) {
            if ($value === self::NOT_SET_VALUE) { // 严格比较,否则遇到数字类型将导致字符串转换为数字比较
                $tmpArray[] = "$field:[* TO *]"; // 字段值不为空
            } else {
                $tmpArray[] = "$field:" . $this->escape($value);
            }

        }
        return $tmpArray ? '(' . join(' AND ', $tmpArray) . ')' : '';
    }

    protected function _parseNeq(array $defineArray)
    {
        $tmpArray = array();
        foreach ($defineArray as $field => $value) {
//            $tmpArray[] = "NOT $field:" . $this->escape($value);
            if ($value === self::NOT_SET_VALUE) { // 严格比较,否则遇到数字类型将导致字符串转换为数字比较
                $filterQuery = "NOT $field:[* TO *]";
                $this->solrQuery->addFilterQuery($filterQuery);
//                $tmpArray[] = "NOT $field:[* TO *]"; // 字段值为空
            } elseif (strlen($value) == 0) { // 针对field != ''的情况
                $tmpArray[] = "$field:[* TO *]";
            } else {
//                $tmpArray[] = "NOT $field:\"$value\""; // 值加上双引号防止含有lucene保留字符
                $filterQuery = "NOT $field:" . $this->escape($value);
                $this->solrQuery->addFilterQuery($filterQuery);
            }
        }
        return $tmpArray ? '(' . join(' AND ', $tmpArray) . ')' : '';
    }

    protected function parseIn(array $defineArray)
    {
        $tmpArray = $tmpArray2 = array();
        foreach ($defineArray as $field => $values) {
            $tmpArray2 = array();
            foreach ($values as $value) {
                $tmpArray2[] = $this->parseEq(array($field => $value));
            }
            if ($tmpArray2) {
                $tmpArray[] = '(' . join(' OR ', $tmpArray2) . ')';
            }
        }
        return $tmpArray ? '(' . join(' AND ', $tmpArray) . ')' : '';
    }

    protected function parseNin(array $defineArray)
    {
        $tmpArray = array();
        foreach ($defineArray as $field => $values) {
            foreach ($values as $value) {
                //$tmpArray[] = $this->_parseNeq(array($field => $value));
                $tmp = $this->_parseNeq(array($field => $value));
                if ($tmp) {
                    $tmpArray[] = $tmp;
                }
            }
        }
        return $tmpArray ? '(' . join(' AND ', $tmpArray) . ')' : '';
    }

    protected function _parseScope(array $defineArray)
    {
        $tmpArray = array();
        foreach ($defineArray as $field => $scope) {
            if (is_array($scope) && isset($scope['min']) && isset($scope['max'])) {
                $tmpArray[] = "$field:[{$scope['min']} TO {$scope['max']}]";
            }
        }
        return $tmpArray ? '(' . join(' AND ', $tmpArray) . ')' : '';
    }

    protected function parseLimit(array $rule)
    {
        $offset = 0;
        $limit = 1; // 默认返回条数
        if (isset($rule['limit']) && is_array($rule['limit'])) {
            $offset = isset($rule['limit'][0]) ? (int)$rule['limit'][0] : $offset;
            $limit = isset($rule['limit'][1]) ? (int)$rule['limit'][1] : $limit;
        }
        $this->solrQuery->setStart($offset);
        $this->solrQuery->setRows($limit);
    }

    public function escape($string)
    {
        // 转义lucene保留字符(转义空格、未转义星号是针对业务需要)
        return addcslashes($string, '+-&|!(){}[]^"~?: \\');
    }

    /**
     * 重置单件模式下的solr对象排序、过滤参数
     *
     * @return void
     */
    public function resetQuery()
    {
        // 重置排序
        $oldSortFields = $this->solrQuery->getSortFields();
        if ($oldSortFields) {
            foreach ($oldSortFields as $field) {
                $field = trim(str_replace(array('asc', 'desc'), '', $field));
                $this->solrQuery->removeSortField($field);
            }
        }
        // 重置过滤条件
        $oldFilterQueries = $this->solrQuery->getFilterQueries();
        if ($oldFilterQueries) {
            foreach ($oldFilterQueries as $filterQuery) {
                $this->solrQuery->removeFilterQuery($filterQuery);
            }
        }
        // 重置facet分类统计
        if ($this->solrQuery->getFacet() && ($oldFacetFields = $this->solrQuery->getFacetFields())) {
            foreach ($oldFacetFields as $facetField) {
                $this->solrQuery->removeFacetField($facetField);
            }
            $this->solrQuery->setFacet(false);
        }
    }

    /**
     * 索引字段分组查询，既能返回分组内条目的数量，
     * 也能返回条目明细
     *
     * @todo 因为返回的条目明细行数默认为1或指定limit，用不上暂时不实现
     *
     * @param array $rule
     */
    protected function parseGroup(array $rule)
    {
        if (isset($rule['group']) && is_array($rule['group']) && $rule['group']) {

        }
    }

    public function __destruct()
    {
        if ($this->inited) {
            $this->solrQuery = null;
            $this->solrClient = null;
            $this->inited = false;
        }
    }
}