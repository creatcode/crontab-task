# Crontab-task

# Thinkphp5.0接口化秒级定时任务

基于 [http-crontab](https://github.com/yzh52521/http-crontab) 修改
## 概述

基于 **Workerman** + **ThinkPHP5.0** 的接口化秒级定时任务管理，兼容 Windows 和 Linux 系统。

* 有crontab表达式解析和校验需求的可以使用 [creatcode/crontab-expression](https://github.com/creatcode/crontab-expression)，支持秒级别表达式的生成和校验



## crontab格式说明：

```
0   1   2   3   4   5
|   |   |   |   |   |
|   |   |   |   |   +------ day of week (0 - 6) (Sunday=0)
|   |   |   |   +------ month (1 - 12)
|   |   |   +-------- day of month (1 - 31)
|   |   +---------- hour (0 - 23)
|   +------------ min (0 - 59)
+-------------- sec (0-59)[可省略，如果没有0位,则最小时间粒度是分钟]
```

```diff
-具体格式说明可查看附录部分crontab表达式详解
```
 

## 命令说明
已注册以下命令，可直接使用
* `php think secron:crontab` 启动定时任务
* `php think secron:makecommand` 创建一个自定义命令类，支持传入命名空间，如 `php think secron:makecommand admin/Test`，默认为命名空间为common
## 任务分类
* url 任务：可以指定一个url地址来请求

* Class 任务：必须指定带有 命名空间的类名，并且实现一个 public 属性的方法：execute 方法返回值为 bool/string类型，支持自定义方法  事例：target='app\\common\\model\\User@check'  @后面是方法 

* Command 任务：请先按照 thinkphp 官方文档定义好执行命令，在新增任务，输入定义的 命令 即可 例如：version

* Shell 任务：输入定义的 shell命令 即可 例如：ps -ef | grep php

* Sql 任务：可直接执行sql语句


 <h1 class="curproject-name"> 定时器接口说明 </h1> 

## PING

<a id=PING> </a>

### 基本信息

**Path：** /crontab/ping

**Method：** GET

**接口描述：**

```
{
     "code": 200,
     "data": "pong",
     "msg": "信息调用成功！"
}
```

### 请求参数

### 返回数据

<table>
  <thead class="ant-table-thead">
    <tr>
      <th key=name>名称</th><th key=type>类型</th>
<th key=required>是否必须</th>
<th key=default>默认值</th>
<th key=desc>备注</th>
<th key=sub>其他信息</th>
    </tr>
  </thead><tbody className="ant-table-tbody"><tr key=0-0><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> code</span></td><td key=1><span>number</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-1><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> data</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr><tr key=0-2><td key=0><span style="padding-left: 0px"><span style="color: #8c8a8a"></span> msg</span></td><td key=1><span>string</span></td><td key=2>非必须</td><td key=3></td><td key=4><span style="white-space: pre-wrap"></span></td><td key=5></td></tr>
               </tbody>
              </table>

## 修改

<a id=修改> </a>

### 基本信息

**Path：** /crontab/modify

**Method：** POST

**接口描述：**

```json
{
  "code": 200,
  "data": true,
  "msg": "信息调用成功！"
}
```

### 请求参数

**Headers**

| 参数名称     | 参数值                            | 是否必须 | 示例 | 备注 |
| ------------ | --------------------------------- | -------- | ---- | ---- |
| Content-Type | application/x-www-form-urlencoded | 是       |      |      |

**Body**

| 参数名称 | 参数类型 | 是否必须 | 示例   | 备注                                   |
| -------- | -------- | -------- | ------ | -------------------------------------- |
| id       | text     | 是       | 1      |                                        |
| field    | text     | 是       | status | 字段[status; sort; remark; title,rule] |
| value    | text     | 是       | 1      | 值                                     |


## 列表

<a id=列表> </a>

### 基本信息

**Path：** /crontab/index

**Method：** GET

**接口描述：**

<pre><code>{
&nbsp; "code": 200,
&nbsp; "data": {
    "total": 4,
&nbsp;&nbsp;&nbsp; "data": [
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "id": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "title": "输出 tp 版本",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "type": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "rule": "*/3 * * * * *",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "target": "version",
        "parameter": "",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "running_times": 3,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "last_running_time": 1625636646,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "remark": "每3秒执行",
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "sort": 0,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "status": 1,
        "singleton": 1,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "create_time": 1625636609,
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; "update_time": 1625636609
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; },
        {
        "id": 2,
        "title": "class任务 每月1号清理所有日志",
        "type": 2,
        "rule": "0 0 1 * *",
        "target": "app\\common\\crontab\\ClearLogCrontab",
        "parameter": "",
        "running_times": 71,
        "last_running_time": 1651121710,
        "remark": "",
        "sort": 0,
        "status": 1,
        "create_time": 1651114277,
        "update_time": 1651114277,
        "singleton": 1
        },
&nbsp;&nbsp;&nbsp; ],
&nbsp; },
&nbsp; "msg": "信息调用成功！"
}
</code></pre>

### 请求参数

**Query**

| 参数名称 | 是否必须 | 示例                     | 备注         |
| -------- | -------- | ------------------------ | ------------ |
| page     | 是       | 1                        | 页码         |
| limit    | 是       | 15                       | 每页条数     |
| filter   | 否       | {"title":"输出 tp 版本"} | 检索字段值   |
| op       | 否       | {"title":"%*%"}          | 检索字段操作 |


## 删除

<a id=删除> </a>

### 基本信息

**Path：** /crontab/delete

**Method：** POST

**接口描述：**

```json
{
     "code": 200,
     "data": true,
     "msg": "信息调用成功！"
}
```


### 请求参数

**Headers**

| 参数名称     | 参数值                            | 是否必须 | 示例 | 备注 |
| ------------ | --------------------------------- | -------- | ---- | ---- |
| Content-Type | application/x-www-form-urlencoded | 是       |      |      |

**Body**

| 参数名称 | 参数类型 | 是否必须 | 示例 | 备注 |
| -------- | -------- | -------- | ---- | ---- |
| id       | text     | 是       | 1,2  |      |


## 定时器池

<a id=定时器池> </a>

### 基本信息

**Path：** /crontab/pool

**Method：** GET

**接口描述：**
```
"code": 200,
"data": [
 {
   "id": 1,
   "type": 1,
   "target": "version",
   "rule": "*/3 * * * * *",
   "parameter": "",
   "remark": "每3秒执行",
   "singleton":1,
   "create_time": "2021-07-07 13:43:29"
 }
],
 "msg": "信息调用成功！"
}
```

### 请求参数


## 日志

<a id=日志> </a>

### 基本信息

**Path：** /crontab/flow

**Method：** GET

**接口描述：**

```
{
  "code": 200,
  "msg": "ok",
  "data": {
    "total": 97,
    "data": [
      {
        "id": 257,
        "crontab_id": 1,
        "target": "version",
        "parameter": "",
        "exception": "v6.0.12LTS",
        "return_code": 0,
        "running_time": "0.834571",
        "create_time": 1651123800,
        "update_time": 1651123800
      },
      {
        "id": 251,
        "crontab_id": 1,
        "target": "version",
        "parameter": "",
        "exception": "v6.0.12LTS",
        "return_code": 0,
        "running_time": "0.540384",
        "create_time": 1651121700,
        "update_time": 1651121700
      },
      {
        "id": 246,
        "crontab_id": 1,
        "target": "version",
        "parameter": "",
        "exception": "v6.0.12LTS",
        "return_code": 0,
        "running_time": "0.316019",
        "create_time": 1651121640,
        "update_time": 1651121640
      },
      {
        "id": 244,
        "crontab_id": 1,
        "target": "version",
        "parameter": "",
        "exception": "v6.0.12LTS",
        "return_code": 0,
        "running_time": "0.493848",
        "create_time": 1651121580,
        "update_time": 1651121580
      }
    ]
  }
}
```
### 请求参数

**Query**

| 参数名称 | 是否必须 | 示例               | 备注         |
| -------- | -------- | ------------------ | ------------ |
| page     | 是       | 1                  | 页码         |
| limit    | 是       | 15                 | 每页条数     |
| filter   | 否       | {"crontab_id":"1"} | 检索字段值   |
| op       | 否       | {"crontab_id":"="} | 检索字段操作 |

## 添加

<a id=添加> </a>

### 基本信息

**Path：** /crontab/add

**Method：** POST

**接口描述：**

```
{
    "code": 200,
    "data": true,
    "msg": "信息调用成功！"
}
```

### 请求参数

**Headers**

| 参数名称     | 参数值                            | 是否必须 | 示例 | 备注 |
| ------------ | --------------------------------- | -------- | ---- | ---- |
| Content-Type | application/x-www-form-urlencoded | 是       |      |      |

**Body**

| 参数名称  | 参数类型 | 是否必须 | 示例               | 备注                                         |
| --------- | -------- | -------- | ------------------ | -------------------------------------------- |
| title     | text     | 是       | 输出 thinkphp 版本 | 任务标题                                     |
| type      | text     | 是       | 1                  | 任务类型 (1 command, 2 class, 3 url,4 shell) |
| rule      | text     | 是       | */3 * * * * *      | 任务执行表达式                               |
| target    | text     | 是       | version            | 调用任务字符串                               |
| parameter | text     | 否       |                    | 调用任务参数(url和shell无效)                 |
| remark    | text     | 是       | 每3秒执行          | 备注                                         |
| sort      | text     | 是       | 0                  | 排序                                         |
| status    | text     | 是       | 1                  | 状态[0禁用; 1启用]                           |
| singleton | text     | 否       | 1                  | 是否单次执行 [0 是 1 不是]                   |


## 重启

<a id=重启> </a>

### 基本信息

**Path：** /crontab/reload

**Method：** POST

**接口描述：**

```json
{
  "code": 200,
  "msg": "信息调用成功",
  "data": {
  }
}
```

### 请求参数

**Headers**

| 参数名称     | 参数值                            | 是否必须 | 示例 | 备注 |
| ------------ | --------------------------------- | -------- | ---- | ---- |
| Content-Type | application/x-www-form-urlencoded | 是       |      |      |

**Body**

| 参数名称 | 参数类型 | 是否必须 | 示例 | 备注                    |
| -------- | -------- | -------- | ---- | ----------------------- |
| id       | text     | 是       | 1,2  | 计划任务ID 多个逗号隔开 |

### 返回数据

```json
{
  "code": 200,
  "msg": "信息调用成功",
  "data": {
  }
}
```          

## 附录

### Cron表达式详解
- 这里是列表文本这里是列表文本Cron表达式是一个字符串，字符串以5或6个空格隔开，分为6或7个域，每一个域代表一个含义，Cron有如下两种语法格式：

（1）Seconds Minutes Hours DayofMonth Month DayofWeek Year

（2）Seconds Minutes Hours DayofMonth Month DayofWeek

### 一、结构

　　corn从左到右（用空格隔开）：秒 分 小时 月份中的日期 月份 星期中的日期 年份

### 二、各字段的含义

|字段   | 允许值  | 允许的特殊字符  |
|---|---|---|
| 秒（Seconds）  | 0~59的整数  | , - * /    四个字符  |
| 分（Minutes）  | 0~59的整数  | , - * /    四个字符  |
| 小时（Hours）  | 0~59的整数  |  , - * /    四个字符 |
| 日期（DayofMonth）  | 1~31的整数（但是你需要考虑你月的天数）  | ,- * ? / L W C     八个字符  |
| 月份（Month）  | 1~12的整数或者 JAN-DEC  | , - * /    四个字符  |
| 星期（DayofWeek）  | 1~7的整数或者 SUN-SAT （1=SUN）  | , - * ? / L C #     八个字符  |
| 年(可选，留空)（Year）  | 1970~2099  | , - * /    四个字符  |

#### 注意事项：

　　每一个域都使用数字，但还可以出现如下特殊字符，它们的含义是：

　　（1）*：表示匹配该域的任意值。假如在Minutes域使用*, 即表示每分钟都会触发事件。

　　（2）?：只能用在DayofMonth和DayofWeek两个域。它也匹配域的任意值，但实际不会。因为DayofMonth和DayofWeek会相互影响。例如想在每月的20日触发调度，不管20日到底是星期几，则只能使用如下写法： 13 13 15 20 * ?, 其中最后一位只能用？，而不能使用*，如果使用*表示不管星期几都会触发，实际上并不是这样。

　　（3）-：表示范围。例如在Minutes域使用5-20，表示从5分到20分钟每分钟触发一次 

　　（4）/：表示起始时间开始触发，然后每隔固定时间触发一次。例如在Minutes域使用5/20,则意味着5分钟触发一次，而25，45等分别触发一次. 

　　（5）,：表示列出枚举值。例如：在Minutes域使用5,20，则意味着在5和20分每分钟触发一次。 

　　（6）L：表示最后，只能出现在DayofWeek和DayofMonth域。如果在DayofWeek域使用5L,意味着在最后的一个星期四触发。 

　　（7）W:表示有效工作日(周一到周五),只能出现在DayofMonth域，系统将在离指定日期的最近的有效工作日触发事件。例如：在 DayofMonth使用5W，如果5日是星期六，则将在最近的    
       工作日：星期五，即4日触发。如果5日是星期天，则在6日(周一)触发；如果5日在星期一到星期五中的一天，则就在5日触发。另外一点，W的最近寻找不会跨过月份 。

　　（8）LW:这两个字符可以连用，表示在某个月最后一个工作日，即最后一个星期五。 

　　（9）#:用于确定每个月第几个星期几，只能出现在DayofWeek域。例如在4#2，表示某月的第二个星期三。

### 三、常用表达式例子

　　（0）0/20 * * * * ?   表示每20秒 调整任务

　　（1）0 0 2 1 * ?   表示在每月的1日的凌晨2点调整任务

　　（2）0 15 10 ? * MON-FRI   表示周一到周五每天上午10:15执行作业

　　（3）0 15 10 ? 6L 2002-2006   表示2002-2006年的每个月的最后一个星期五上午10:15执行作

　　（4）0 0 10,14,16 * * ?   每天上午10点，下午2点，4点 

　　（5）0 0/30 9-17 * * ?   朝九晚五工作时间内每半小时 

　　（6）0 0 12 ? * WED    表示每个星期三中午12点 

　　（7）0 0 12 * * ?   每天中午12点触发 

　　（8）0 15 10 ? * *    每天上午10:15触发 

　　（9）0 15 10 * * ?     每天上午10:15触发 

　　（10）0 15 10 * * ? *    每天上午10:15触发 

　　（11）0 15 10 * * ? 2005    2005年的每天上午10:15触发 

　　（12）0 * 14 * * ?     在每天下午2点到下午2:59期间的每1分钟触发 

　　（13）0 0/5 14 * * ?    在每天下午2点到下午2:55期间的每5分钟触发 

　　（14）0 0/5 14,18 * * ?     在每天下午2点到2:55期间和下午6点到6:55期间的每5分钟触发 

　　（15）0 0-5 14 * * ?    在每天下午2点到下午2:05期间的每1分钟触发 

　　（16）0 10,44 14 ? 3 WED    每年三月的星期三的下午2:10和2:44触发 

　　（17）0 15 10 ? * MON-FRI    周一至周五的上午10:15触发 

　　（18）0 15 10 15 * ?    每月15日上午10:15触发 

　　（19）0 15 10 L * ?    每月最后一日的上午10:15触发 

　　（20）0 15 10 ? * 6L    每月的最后一个星期五上午10:15触发 

　　（21）0 15 10 ? * 6L 2002-2005   2002年至2005年的每月的最后一个星期五上午10:15触发 

　　（22）0 15 10 ? * 6#3   每月的第三个星期五上午10:15触发

　　注：

　　（1）有些子表达式能包含一些范围或列表

　　例如：子表达式（天（星期））可以为 “MON-FRI”，“MON，WED，FRI”，“MON-WED,SAT”

“*”字符代表所有可能的值

　　因此，“*”在子表达式（月）里表示每个月的含义，“*”在子表达式（天（星期））表示星期的每一天


　　“/”字符用来指定数值的增量 
　　例如：在子表达式（分钟）里的“0/15”表示从第0分钟开始，每15分钟 
在子表达式（分钟）里的“3/20”表示从第3分钟开始，每20分钟（它和“3，23，43”）的含义一样


　　“？”字符仅被用于天（月）和天（星期）两个子表达式，表示不指定值 
　　当2个子表达式其中之一被指定了值以后，为了避免冲突，需要将另一个子表达式的值设为“？”

　　“L” 字符仅被用于天（月）和天（星期）两个子表达式，它是单词“last”的缩写 
　　但是它在两个子表达式里的含义是不同的。 
　　在天（月）子表达式中，“L”表示一个月的最后一天 
　　在天（星期）自表达式中，“L”表示一个星期的最后一天，也就是SAT

　　如果在“L”前有具体的内容，它就具有其他的含义了

　　例如：“6L”表示这个月的倒数第６天，“FRIL”表示这个月的最一个星期五 
　　注意：在使用“L”参数时，不要指定列表或范围，因为这会导致问题
