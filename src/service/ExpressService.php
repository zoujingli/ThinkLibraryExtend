<?php

declare (strict_types=1);

namespace think\admin\service;

use think\admin\extend\CodeExtend;
use think\admin\extend\HttpExtend;
use think\admin\Service;

/**
 * 百度快递100物流查询
 * Class ExpressService
 * @package think\admin\service
 */
class ExpressService extends Service
{

    /**
     * 网络请求参数
     * @var array
     */
    protected $options;

    /**
     *  当前COOKIE文件
     * @var string
     */
    protected $cookies;

    /**
     * 快递服务初始化
     * @return $this
     */
    public function initialize(): ExpressService
    {
        // 创建 CURL 请求模拟参数
        $clentip = $this->app->request->ip();
        $this->cookies = "{$this->app->getRootPath()}runtime/.express.cookie";
        $this->options = ['cookie_file' => $this->cookies, 'headers' => [
            'Host:express.baidu.com', "CLIENT-IP:{$clentip}", "X-FORWARDED-FOR:{$clentip}",
        ]];
        // 每 10 秒重置 cookie 文件
        if (file_exists($this->cookies) && filectime($this->cookies) + 10 < time()) {
            @unlink($this->cookies);
        }
        return $this;
    }

    /**
     * 通过百度快递100应用查询物流信息
     * @param string $code 快递公司编辑
     * @param string $numb 快递物流编号
     * @param array $list 快递路径列表
     * @return array
     */
    public function express(string $code, string $numb, array $list = []): array
    {
        // 1-新订单,2-在途中,3-签收,4-问题件
        // 0-在途，1-揽收，2-疑难，3-签收，4-退签，5-派件，6-退回
        $ckey = md5("{$code}{$numb}");
        $cache = $this->app->cache->get($ckey, []);
        if (!empty($cache)) return $cache;
        for ($i = 0; $i < 6; $i++) if (is_array($result = $this->doExpress($code, $numb))) {
            if (isset($result['data']['info']['context']) && isset($result['data']['info']['state'])) {
                $state = intval($result['data']['info']['state']);
                $status = in_array($state, [0, 1, 5]) ? 2 : ($state === 3 ? 3 : 4);
                foreach ($result['data']['info']['context'] as $vo) {
                    $list[] = ['time' => date('Y-m-d H:i:s', intval($vo['time'])), 'context' => $vo['desc']];
                }
                $result = ['message' => $result['msg'], 'status' => $status, 'express' => $code, 'number' => $numb, 'data' => $list];
                $this->app->cache->set($ckey, $result, 10);
                return $result;
            }
        }
        return ['message' => '暂无轨迹信息', 'status' => 1, 'express' => $code, 'number' => $numb, 'data' => $list];
    }

    /**
     * 获取快递公司列表
     * @param array $data
     * @return array
     */
    public function getExpressList(array $data = []): array
    {
        if (preg_match('/"currentData":.*?\[(.*?)],/', $this->_getWapBaiduHtml(), $matches)) {
            foreach (json_decode("[{$matches['1']}]") as $item) $data[$item->value] = $item->text;
            unset($data['_auto']);
            return $data;
        } else {
            @unlink($this->cookies);
            $this->app->cache->delete('express_kuaidi_html');
            return $this->getExpressList();
        }
    }

    /**
     * 执行百度快递100应用查询请求
     * @param string $code 快递公司编号
     * @param string $number 快递单单号
     * @return mixed
     */
    private function doExpress(string $code, string $number)
    {
        $qid = CodeExtend::uniqidTime(19, '7740');
        $url = "{$this->_getExpressQueryApi()}&appid=4001&nu={$number}&com={$code}&qid={$qid}&new_need_di=1&source_xcx=0&vcode=&token=&sourceId=4155&cb=callback";
        return json_decode(str_replace('/**/callback(', '', trim(HttpExtend::get($url, [], $this->options), ')')), true);
    }

    /**
     * 获取快递查询接口
     * @return string
     */
    private function _getExpressQueryApi(): string
    {
        if (preg_match('/"expSearchApi":.*?"(.*?)",/', $this->_getWapBaiduHtml(), $matches)) {
            return str_replace('\\', '', $matches[1]);
        } else {
            @unlink($this->cookies);
            $this->app->cache->delete('express_kuaidi_html');
            return $this->_getExpressQueryApi();
        }
    }

    /**
     * 获取百度WAP快递HTML（用于后面的抓取关键值）
     * @return string
     */
    private function _getWapBaiduHtml(): string
    {
        $content = $this->app->cache->get('express_kuaidi_html', '');
        while (empty($content) || stripos($content, '"expSearchApi":') === -1) {
            $uniqid = str_replace('.', '', microtime(true));
            $content = HttpExtend::get("https://m.baidu.com/s?word=快递查询&rand={$uniqid}", [], $this->options);
        }
        $this->app->cache->set('express_kuaidi_html', $content, 10);
        return $content;
    }

}