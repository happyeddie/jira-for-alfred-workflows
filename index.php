<?php
require_once 'workflows.php';
require_once 'config.php';
$w      = new Workflows();
$kw     = "{query}";
$isKey  = preg_match("/^([a-z]+)-([\d]+)$/i", $kw);
if ($isKey) {
    $query  = urlencode("key = {$kw}");
}
else {
    $query  = urlencode("summary ~ {$kw} OR description ~ {$kw}");
}

$url    = "{$jiraUrl}/sr/jira.issueviews:searchrequest-xml/temp/SearchRequest.xml?tempMax=20&jqlQuery={$query}";
$content        = $w->request($url, array(
    CURLOPT_COOKIE  => $jiraCookie,
));

if (!strpos($content, 'channel')) {
    $w->result('zhoufan.jira.error', '', '帐户验证失败', '请先运行JIRA参数设置', 'icon.png');
    echo $w->toxml();
    exit;
}

preg_match_all("/<item>([\S\s]+?)<\/item>/", $content, $itemLines);

if (!count($itemLines[1])) {
    $w->result('zhoufan.jira.no_result', '', '没有查询到相关记录', '', 'icon.png');
    echo $w->toxml();
    exit;
}
foreach ((array) $itemLines[1] as $itemLine) {
    $itemLine   = trim($itemLine);
    $itemLine   = preg_replace("/>([\s]+?)</", '><', $itemLine);
    
    preg_match_all("/<([\S\s]+?)>([\S\s]*?)<\/([\S]+?)>/", $itemLine, $itemVars);

    foreach ($itemVars[1] as $itemIndex => $itemName) {
        preg_match_all("/([\S]+?)=\"([\S]+?)\"/", $itemName, $matches);
        foreach ($matches[1] as $index => $key) {
            $itemInfo[$itemVars[3][$itemIndex]][$key]   = $matches[2][$index];
        }
    }
    foreach ($itemVars[3] as $itemIndex => $itemName) {
        $itemInfo[$itemName]['value']   = $itemVars[2][$itemIndex];
        $itemInfo[$itemName]['value']   = html_entity_decode($itemInfo[$itemName]['value'], ENT_NOQUOTES, 'UTF-8');
        $itemInfo[$itemName]['value']   = strip_tags($itemInfo[$itemName]['value']);
    }
    
    $key        = $itemInfo['key']['value'];
    $url        = $itemInfo['link']['value'];
    $title      = $itemInfo['title']['value'];
    $reporter   = substr($itemInfo['reporter']['value'], strlen($itemInfo['reporter']['username']) + 1);
    $assignee   = substr($itemInfo['assignee']['value'], strlen($itemInfo['assignee']['username']) + 1);
    $desc       = "类型:{$itemInfo['type']['value']} 优先级:{$itemInfo['priority']['value']} 状态:{$itemInfo['status']['value']} 报告:{$reporter} 经办:{$assignee}";
    $w->result($key, $url, $title, $desc, 'icon.png');
}

echo $w->toxml();