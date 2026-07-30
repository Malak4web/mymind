<?php

namespace Database\Seeders;

use App\Models\Attachment;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\ProjectTemplate;
use App\Models\TaskTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Seed Templates
        ProjectTemplate::create([
            'name' => 'القالب الافتراضي للمشاريع',
            'is_default' => true,
            'statuses' => ['بانتظار البدء', 'قيد العمل', 'تحت المراجعة', 'مكتمل'],
            'custom_fields' => [
                ['name' => 'رابط ملف فيجما UX', 'type' => 'link'],
                ['name' => 'الساعات المقدرة للمهمة', 'type' => 'number']
            ]
        ]);

        TaskTemplate::create([
            'name' => 'تصميم واجهة المستخدم (Figma)',
            'is_default' => false,
            'title' => 'تصميم واجهات المستخدم للمشروع',
            'description' => 'تصميم لوحات ومكونات واجهة المستخدم على Figma وتجهيز الـ Prototype لمراجعته مع العميل.',
            'status' => 'قيد العمل',
            'custom_fields_values' => [
                'رابط ملف فيجما UX' => 'https://figma.com/file/mymind-design'
            ]
        ]);

        // 1. Seed Permissions
        $manageProjects = Permission::create(['name' => 'إدارة المشاريع', 'slug' => 'manage-projects']);
        $manageTasks = Permission::create(['name' => 'إدارة المهام', 'slug' => 'manage-tasks']);
        $manageUsers = Permission::create(['name' => 'إدارة المستخدمين', 'slug' => 'manage-users']);

        // 2. Seed Roles & Link Permissions
        $roleAdmin = Role::create([
            'name' => 'مدير',
            'description' => 'صلاحيات كاملة لإدارة المشاريع والمهام وإعدادات المستخدمين.'
        ]);
        $roleAdmin->permissions()->attach([$manageProjects->id, $manageTasks->id, $manageUsers->id]);

        $roleMember = Role::create([
            'name' => 'عضو',
            'description' => 'صلاحيات كاملة للعمل على المشاريع والمهام، مع حظر الوصول لإدارة النظام والمستخدمين.'
        ]);
        $roleMember->permissions()->attach([$manageProjects->id, $manageTasks->id]);

        $roleViewer = Role::create([
            'name' => 'مشاهد',
            'description' => 'صلاحيات العرض والقراءة فقط للمشاريع واللوحات دون القدرة على التعديل.'
        ]);

        // 3. Seed Users
        $userKhaled = User::create([
            'name' => 'خالد (مدير المشروع)',
            'email' => 'khaled@mymind.com',
            'password' => Hash::make('password123'),
            'role_id' => $roleAdmin->id
        ]);

        $userSara = User::create([
            'name' => 'سارة (مصممة)',
            'email' => 'sara@mymind.com',
            'password' => Hash::make('password123'),
            'role_id' => $roleMember->id
        ]);

        $userAhmed = User::create([
            'name' => 'أحمد العشري',
            'email' => 'ahmed@mymind.com',
            'password' => Hash::make('password123'),
            'role_id' => $roleViewer->id
        ]);

        // 4. Seed Projects
        $project1 = Project::create([
            'name' => 'بناء الهوية البصرية',
            'description' => 'تطوير وتصميم الهوية البصرية واللوغو والدليل الإرشادي الكامل للشركة.',
            'statuses' => ['بانتظار البدء', 'قيد العمل', 'تحت المراجعة', 'مكتمل']
        ]);

        $project2 = Project::create([
            'name' => 'تصميم واجهات موقع العميل',
            'description' => 'تصميم واجهات وتجربة المستخدم لموقع التجارة الإلكترونية الجديد باستخدام فيجما.',
            'statuses' => ['مسودة أولية', 'تصميم نشط', 'مرحلة الاعتماد', 'جاهز للمطورين']
        ]);

        $project3 = Project::create([
            'name' => 'حملة التسويق الرقمي',
            'description' => 'تخطيط وإطلاق الحملات الإعلانية على منصات التواصل الاجتماعي ومحركات البحث.',
            'statuses' => ['مجدول', 'حملة نشطة', 'تحليل النتائج', 'مكتمل']
        ]);

        // 5. Seed Custom Fields definitions
        $fieldFigma = CustomFieldDefinition::create([
            'project_id' => $project1->id,
            'name' => 'رابط ملف فيجما UX',
            'type' => 'link',
            'active' => true
        ]);

        $fieldHours = CustomFieldDefinition::create([
            'project_id' => $project1->id,
            'name' => 'الساعات المقدرة للمهمة',
            'type' => 'number',
            'active' => true
        ]);

        // 6. Seed Tasks for Project 1
        $task1 = Task::create([
            'project_id' => $project1->id,
            'title' => 'رسم الشعار الأولي والمسودات',
            'description' => 'إعداد 3 مسودات أولية للشعار باللونين الأبيض والأسود.',
            'status' => 'مكتمل',
            'start_date' => '2026-07-01',
            'deadline' => '2026-07-06'
        ]);

        $task2 = Task::create([
            'project_id' => $project1->id,
            'title' => 'تحديد الخطوط واللوحة اللونية',
            'description' => 'تحديد الخطوط الأساسية والثانوية ولوحة الألوان الرئيسية للهوية.',
            'status' => 'قيد العمل',
            'start_date' => '2026-07-08',
            'deadline' => '2026-07-15'
        ]);

        $task3 = Task::create([
            'project_id' => $project1->id,
            'title' => 'مراجعة العميل لشكل الشعار المعتمد',
            'description' => 'عرض اللوغو المطور في ملف عرض تفاعلي على العميل لأخذ التغذية الراجعة.',
            'status' => 'تحت المراجعة',
            'start_date' => '2026-07-16',
            'deadline' => '2026-07-22'
        ]);

        $task4 = Task::create([
            'project_id' => $project1->id,
            'title' => 'تسليم دليل استخدام الهوية البصرية',
            'description' => 'تصدير الدليل الكامل بصيغة PDF وملفات المتجهات الأصلية.',
            'status' => 'بانتظار البدء',
            'start_date' => null,
            'deadline' => null
        ]);

        // 7. Seed Custom Fields values
        CustomFieldValue::create([
            'task_id' => $task3->id,
            'custom_field_definition_id' => $fieldFigma->id,
            'value' => 'https://figma.com/file/identity-drafts'
        ]);

        CustomFieldValue::create([
            'task_id' => $task2->id,
            'custom_field_definition_id' => $fieldHours->id,
            'value' => '16'
        ]);

        // 8. Seed Attachments
        Attachment::create([
            'task_id' => $task1->id,
            'name' => 'logo-sketches-v1.png',
            'path' => 'attachments/logo-sketches-v1.png',
            'size' => '1.8 MB',
            'progress' => 100,
            'status' => 'done'
        ]);

        Attachment::create([
            'task_id' => $task3->id,
            'name' => 'brand-presentation.pdf',
            'path' => 'attachments/brand-presentation.pdf',
            'size' => '4.2 MB',
            'progress' => 100,
            'status' => 'done'
        ]);

        // 9. Seed Messages
        Message::create([
            'project_id' => $project1->id,
            'task_id' => null,
            'sender' => 'خالد (مدير المشروع)',
            'text' => 'مرحباً بالفريق، سنبدأ اليوم بالتركيز على تسليم خطوط الهوية البصرية والألوان في الموعد المحدد.'
        ]);

        $msg1 = Message::create([
            'project_id' => $project1->id,
            'task_id' => null,
            'sender' => 'سارة (مصممة)',
            'text' => 'أهلاً خالد، لقد أتممت لوحة الألوان الأساسية وسأرفعها للمراجعة بعد قليل.'
        ]);

        Message::create([
            'project_id' => $project1->id,
            'task_id' => null,
            'sender' => 'أنت',
            'text' => 'رائع سارة، سأقوم بمراجعة التناسق مع واجهات الموقع فور رفعها.',
            'reply_to_id' => $msg1->id
        ]);

        // 10. Seed Notifications
        Notification::create([
            'title' => 'مهمة جديدة مضافة',
            'text' => 'تم إسناد مهمة "تحديد الخطوط واللوحة اللونية" إليك بواسطة خالد (مدير المشروع).',
            'is_read' => false
        ]);
    }
}
