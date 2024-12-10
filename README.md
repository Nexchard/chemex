# 说明：

本项目基于[celaraze/chemex](https://github.com/celaraze/chemex)，原作者已停止维护，为满足个人使用需求，进行了部分修改。

### 修改包含：

1. app/Services/LDAPService.php
2. resources/views/print_tag_label_pdf.blade.php

### 修改说明：

1. 调整获取OU和User逻辑，解决OU因name字段重复导致OU结构错乱的问题，解决User获取上限1000位用户的问题。
2. 调整资产标签中的展示信息

### 部署时注意事项：

1. 调整php配置中的max_execution_time、memory_limit。如果用户较多，同步时间较长，这两项值过小会导致同步中断或失败。这两项没有细致测试，我粗暴的配置为memory_limit=4096M、max_execution_time=1000（单位秒），测试环境用户约5300+、OU约940+，足够满足要求。
2. 假如使用宝塔部署，php扩展需安装ldap和fileinfo。

