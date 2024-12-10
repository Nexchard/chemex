<?php

namespace App\Services;

use Adldap\Laravel\Facades\Adldap;
use App\Models\Department;
use App\Models\ImportLog;
use App\Models\ImportLogDetail;
use App\Models\User;
use Dcat\Admin\Admin;
use Exception;

class LDAPService
{
    /**
     * 获取AD中全部的OU，并且带上层级.
     *
     * @param $mode
     *
     * @return int[]
     */
    public static function importUserDepartments($mode): array
    {
        $success = 0;
        $fail = 0;

        $import_log = new ImportLog();
        $import_log->item = get_class(new Department());
        $import_log->operator = Admin::user()->id;
        $import_log->save();

        try {
            // 如果模式是复写，先执行清空表
            if ($mode == 'rewrite') {
                Department::truncate();
            }
            $ous = Adldap::search()->ous()->get();
            $ous = json_decode($ous, true);
            // 遍历所有的OU
            foreach ($ous as $ou) {
                // 使用DN作为唯一标识符
                $ou_dn = $ou['distinguishedname'][0];
                $ou_name = $ou['name'][0];
                $ou_level_array = explode(',', $ou_dn);
                $parent_department_id = 0;

                // 逐级创建或获取父级OU
                for ($i = count($ou_level_array) - 1; $i >= 0; $i--) {
                    if (str_contains($ou_level_array[$i], 'OU=')) {
                        $current_ou_name = explode('=', $ou_level_array[$i])[1];
                        $current_ou_dn = implode(',', array_slice($ou_level_array, $i));
                        $current_department = Department::where('name', $current_ou_name)
                            ->where('parent_id', $parent_department_id)
                            ->where('ad_tag', 1)
                            ->first();
                        if (empty($current_department)) {
                            $current_department = new Department();
                            $current_department->name = $current_ou_name; // 仅存储OU名称
                            $current_department->parent_id = $parent_department_id;
                            $current_department->ad_tag = 1;
                            $current_department->save();
                        }
                        $parent_department_id = $current_department->id;
                    }
                }

                $success++;
                ImportLogDetail::query()->create([
                    'log_id' => $import_log->id,
                    'status' => 1,
                    'log' => $ou_name . '：导入成功！'
                ]);
            }
        } catch (Exception $exception) {
            $fail++;
            // 导入日志写入
            ImportLogDetail::query()->create([
                'log_id' => $import_log->id,
                'log' => '未知：导入失败，' . $exception->getMessage()
            ]);
        }
        return [$success, $fail];
    }

    /**
     * 获取AD中全部的User，并且自动写入部门.
     *
     * @param $mode
     *
     * @return int[]|string
     */
    public static function importUsers($mode): array|string
    {
        $success = 0;
        $fail = 0;

        $import_log = new ImportLog();
        $import_log->item = get_class(new User());
        $import_log->operator = Admin::user()->id;
        $import_log->save();

        try {
            if ($mode == 'rewrite') {
                User::truncate();
            }

            $page = 1;
            $perPage = 1000;
            do {
                $paginator = Adldap::search()->users()->paginate($perPage, $page);
                $users = $paginator->getResults();

                foreach ($users as $user) {
                    $user_account = $user->getAccountName();
                    $user_name = $user->getCommonName();
                    $user_dns = $user->getDistinguishedName();
                    $user_dn_array = explode(',', $user_dns);
                    $user_dn_up = $user_dn_array[1];
                    $department_id = 0;

                    if (str_contains($user_dn_up, 'OU=')) {
                        $user_dn_department = explode('=', $user_dn_up)[1];
                        $department = Department::where('name', $user_dn_department)->first();
                        if (!empty($department)) {
                            $department_id = $department->id;
                        }
                    }

                    $existing_user = User::withTrashed()->where('username', $user_account)->first();
                    if (empty($existing_user)) {
                        $new_user = new User();
                        $new_user->username = $user_account;
                        $new_user->password = bcrypt($user_account);
                        $new_user->name = $user_name;
                        $new_user->department_id = $department_id;
                        $new_user->ad_tag = 1;
                        $new_user->save();
                        $success++;
                        ImportLogDetail::query()->create([
                            'log_id' => $import_log->id,
                            'status' => 1,
                            'log' => $user_name . '：导入成功！'
                        ]);
                    }
                }
                $page++;
            } while (count($users) == $perPage);
        } catch (Exception $exception) {
            $fail++;
            ImportLogDetail::query()->create([
                'log_id' => $import_log->id,
                'log' => '未知：导入失败，' . $exception->getMessage()
            ]);
        }

        return [$success, $fail];
    }
}
