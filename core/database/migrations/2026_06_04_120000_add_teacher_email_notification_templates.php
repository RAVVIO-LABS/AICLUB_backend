<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $templates = [
            [
                'act' => 'TEACHER_FREE_CLASS_ASSIGNED',
                'name' => 'Teacher Free Class Assigned',
                'subject' => 'Teacher Assignment: New Free Class Scheduled for {{student_name}}',
                'email_body' => '<div>Hello {{teacher_name}},</div><div><br></div><div>You have been assigned to conduct a free class for the following student:</div><div><br></div><div><b>Student Name:</b> {{student_name}}</div><div><b>Grade:</b> {{grade}}</div><div><b>Email ID:</b> {{student_email}}</div><div><b>Phone Number:</b> {{student_phone}}</div><div><b>Date:</b> {{class_date}}</div><div><b>Time:</b> {{class_time}}</div><div><br></div><div>Please review the student details and prepare accordingly.</div><div><br></div><div>Thank you,</div><div>AIClub India</div>',
                'shortcodes' => [
                    'teacher_name' => 'Teacher full name',
                    'student_name' => 'Student name',
                    'grade' => 'Student grade',
                    'student_email' => 'Student email',
                    'student_phone' => 'Student phone number',
                    'class_date' => 'Scheduled class date',
                    'class_time' => 'Scheduled class time',
                ],
            ],
            [
                'act' => 'TEACHER_FREE_CLASS_CANCELLED_UNAVAILABLE',
                'name' => 'Teacher Free Class Cancelled Unavailable',
                'subject' => 'Free Class Cancellation: {{student_name}}',
                'email_body' => '<div>Hello {{teacher_name}},</div><div><br></div><div>The following free class has been cancelled due to your unavailability:</div><div><br></div><div><b>Student Name:</b> {{student_name}}</div><div><b>Grade:</b> {{grade}}</div><div><b>Scheduled Date:</b> {{class_date}}</div><div><b>Scheduled Time:</b> {{class_time}}</div><div><br></div><div>Thank you,</div><div>AIClub India</div>',
                'shortcodes' => [
                    'teacher_name' => 'Teacher full name',
                    'student_name' => 'Student name',
                    'grade' => 'Student grade',
                    'class_date' => 'Scheduled class date',
                    'class_time' => 'Scheduled class time',
                ],
            ],
            [
                'act' => 'TEACHER_BATCH_CREATED',
                'name' => 'Teacher Batch Created',
                'subject' => 'New Batch Created and Assigned',
                'email_body' => '<div>Hello {{teacher_name}},</div><div><br></div><div>A new batch has been created based on your submitted availability.</div><div><br></div><div><b>Batch Name:</b> {{batch_name}}</div><div><b>Course Name:</b> {{course_name}}</div><div><b>Grade Group:</b> {{grade_range}}</div><div><b>Start Date:</b> {{start_date}}</div><div><b>End Date:</b> {{end_date}}</div><div><b>Session Schedule:</b> {{session_schedule}}</div><div><br></div><div>Please review the batch details and inform the coordination team if you have any concerns or questions.</div><div><br></div><div>Thank you,</div><div>AIClub India</div>',
                'shortcodes' => [
                    'teacher_name' => 'Teacher full name',
                    'batch_name' => 'Batch name',
                    'course_name' => 'Course title',
                    'grade_range' => 'Grade range or group',
                    'start_date' => 'Batch start date',
                    'end_date' => 'Batch end date',
                    'session_schedule' => 'Batch session schedule',
                ],
            ],
            [
                'act' => 'TEACHER_BATCH_ASSIGNED',
                'name' => 'Teacher Batch Assigned',
                'subject' => 'Batch Assignment Notification: {{batch_name}}',
                'email_body' => '<div>Hello {{teacher_name}},</div><div><br></div><div>You have been assigned to the following batch:</div><div><br></div><div><b>Batch Name:</b> {{batch_name}}</div><div><b>Course Name:</b> {{course_name}}</div><div><b>Grade Group:</b> {{grade_range}}</div><div><b>Start Date:</b> {{start_date}}</div><div><b>End Date:</b> {{end_date}}</div><div><b>Session Schedule:</b> {{session_schedule}}</div><div><br></div><div>Please review the batch details and contact the coordination team if you have any questions.</div><div><br></div><div>Thank you,</div><div>AIClub India</div>',
                'shortcodes' => [
                    'teacher_name' => 'Teacher full name',
                    'batch_name' => 'Batch name',
                    'course_name' => 'Course title',
                    'grade_range' => 'Grade range or group',
                    'start_date' => 'Batch start date',
                    'end_date' => 'Batch end date',
                    'session_schedule' => 'Batch session schedule',
                ],
            ],
            [
                'act' => 'TEACHER_BATCH_CANCELLED',
                'name' => 'Teacher Batch Cancelled',
                'subject' => 'Batch Cancellation Notification: {{batch_name}}',
                'email_body' => '<div>Hello {{teacher_name}},</div><div><br></div><div>The following batch has been cancelled and removed from the schedule:</div><div><br></div><div><b>Batch Name:</b> {{batch_name}}</div><div><b>Course Name:</b> {{course_name}}</div><div><b>Start Date:</b> {{start_date}}</div><div><b>End Date:</b> {{end_date}}</div><div><br></div><div><b>Reason:</b><br>{{reason}}</div><div><br></div><div>Thank you,</div><div>AIClub India</div>',
                'shortcodes' => [
                    'teacher_name' => 'Teacher full name',
                    'batch_name' => 'Batch name',
                    'course_name' => 'Course title',
                    'start_date' => 'Batch start date',
                    'end_date' => 'Batch end date',
                    'reason' => 'Batch cancellation reason',
                ],
            ],
            [
                'act' => 'TEACHER_FREE_CLASS_REASSIGNED',
                'name' => 'Teacher Free Class Reassigned',
                'subject' => 'New Free Class Assignment: {{student_name}}',
                'email_body' => '<div>Hello {{teacher_name}},</div><div><br></div><div>You have been assigned to conduct the following free class.</div><div><br></div><div><b>Student Details:</b></div><div><b>Student Name:</b> {{student_name}}</div><div><b>Grade:</b> {{grade}}</div><div><b>Email ID:</b> {{student_email}}</div><div><b>Phone Number:</b> {{student_phone}}</div><div><b>Date:</b> {{class_date}}</div><div><b>Time:</b> {{class_time}}</div><div><br></div><div>Please note that this class was previously assigned to another teacher and has now been reassigned to you.</div><div><br></div><div>Kindly review the details and prepare for the session.</div><div><br></div><div>Thank you,</div><div>AI Club Operations Team</div>',
                'shortcodes' => [
                    'teacher_name' => 'Teacher full name',
                    'student_name' => 'Student name',
                    'grade' => 'Student grade',
                    'student_email' => 'Student email',
                    'student_phone' => 'Student phone number',
                    'class_date' => 'Scheduled class date',
                    'class_time' => 'Scheduled class time',
                ],
            ],
        ];

        foreach ($templates as $item) {
            $exists = DB::table('notification_templates')->where('act', $item['act'])->exists();
            if ($exists) {
                DB::table('notification_templates')
                    ->where('act', $item['act'])
                    ->update([
                        'name' => $item['name'],
                        'subject' => $item['subject'],
                        'email_body' => $item['email_body'],
                        'shortcodes' => json_encode($item['shortcodes']),
                        'email_status' => 1,
                        'updated_at' => now(),
                    ]);
                continue;
            }

            DB::table('notification_templates')->insert([
                'act' => $item['act'],
                'name' => $item['name'],
                'subject' => $item['subject'],
                'push_title' => null,
                'email_body' => $item['email_body'],
                'sms_body' => null,
                'shortcodes' => json_encode($item['shortcodes']),
                'email_status' => 1,
                'email_sent_from_name' => null,
                'email_sent_from_address' => null,
                'sms_status' => 0,
                'sms_sent_from' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->whereIn('act', [
            'TEACHER_FREE_CLASS_ASSIGNED',
            'TEACHER_FREE_CLASS_CANCELLED_UNAVAILABLE',
            'TEACHER_BATCH_CREATED',
            'TEACHER_BATCH_ASSIGNED',
            'TEACHER_BATCH_CANCELLED',
            'TEACHER_FREE_CLASS_REASSIGNED',
        ])->delete();
    }
};
