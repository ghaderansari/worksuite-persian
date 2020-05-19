<?php

namespace App\Http\Controllers\Member;

use App\Helper\Reply;
use App\Http\Requests\Tasks\StoreTask;
use App\Notifications\NewTask;
use App\Notifications\TaskCompleted;
use App\Notifications\TaskReminder;
use App\Notifications\TaskUpdated;
use App\Project;
use App\ProjectMember;
use App\Task;
use App\TaskboardColumn;
use App\TaskCategory;
use App\TaskFile;
use App\Traits\ProjectProgress;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use App\Notifications\NewClientTask;
use App\TaskUser;
use Illuminate\Support\Facades\Notification;


class MemberAllTasksController extends MemberBaseController
{
    use ProjectProgress;

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = __('app.menu.tasks');
        $this->pageIcon = 'fa fa-tasks';
        $this->middleware(function ($request, $next) {
            if (!in_array('tasks', $this->user->modules)) {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index()
    {
        $this->projects = ($this->user->can('view_projects')) ? Project::orderBy('project_name', 'asc')->get() : Project::select('projects.*')->join('project_members', 'project_members.project_id', '=', 'projects.id')
            ->where('project_members.user_id', $this->user->id)
            ->orderBy('project_name', 'asc')
            ->get();
        $this->employees = ($this->user->can('view_employees')) ? User::allEmployees() : User::where('id', $this->user->id)->get();

        $this->clients = User::allClients();
        $this->taskBoardStatus = TaskboardColumn::all();
        
        $this->startDate = Carbon::today()->subDays(15)->format($this->global->date_format);
        $this->endDate = Carbon::today()->addDays(15)->format($this->global->date_format);

        return view('member.all-tasks.index', $this->data);
    }

    public function data(Request $request, $startDate = null, $endDate = null, $hideCompleted = null, $projectId = null)
    {
        $startDate = Carbon::createFromFormat($this->global->date_format, $request->startDate)->toDateString();
        $endDate = Carbon::createFromFormat($this->global->date_format, $request->endDate)->toDateString();
        $projectId =  $request->projectId;
        $hideCompleted = $request->hideCompleted;

        $taskBoardColumn = TaskboardColumn::where('slug', 'incomplete')->first();

        $tasks = Task::leftJoin('projects', 'projects.id', '=', 'tasks.project_id')
            ->join('task_users', 'task_users.task_id', '=', 'tasks.id')
            ->join('users as member', 'task_users.user_id', '=', 'member.id')
            ->leftJoin('users as creator_user', 'creator_user.id', '=', 'tasks.created_by')
            ->join('taskboard_columns', 'taskboard_columns.id', '=', 'tasks.board_column_id')
            ->select('tasks.id', 'projects.project_name', 'tasks.heading', 'creator_user.image as created_image', 'tasks.due_date', 'taskboard_columns.column_name as board_column', 'taskboard_columns.label_color', 'tasks.project_id', 'tasks.is_private', 'tasks.created_by')
            ->whereNull('projects.deleted_at')
            ->with('users', 'users.user')
            ->groupBy('tasks.id');

        $tasks->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween(DB::raw('DATE(tasks.`due_date`)'), [$startDate, $endDate]);

            $q->orWhereBetween(DB::raw('DATE(tasks.`start_date`)'), [$startDate, $endDate]);
        });

        if ($projectId != 0 && $projectId !=  null && $projectId !=  'all') {
            $tasks->where('tasks.project_id', '=', $projectId);
        }

        if ($request->assignedTo != '' && $request->assignedTo !=  null && $request->assignedTo !=  'all') {
            $tasks->where('task_users.user_id', '=', $request->assignedTo);
        }

        if ($request->assignedBY != '' && $request->assignedBY !=  null && $request->assignedBY !=  'all') {
            $tasks->where('creator_user.id', '=', $request->assignedBY);
        }

        if ($request->status != '' && $request->status !=  null && $request->status !=  'all') {
            $tasks->where('tasks.board_column_id', '=', $request->status);
        }
        if ($hideCompleted == '1') {
            $tasks->where('tasks.board_column_id', $taskBoardColumn->id);
        }
        if (!$this->user->can('view_tasks')) {
            // $tasks->where('tasks.is_private', 0);
            $tasks->where(function ($q) {
                $q->where('task_users.user_id', $this->user->id);
                $q->orWhere('tasks.created_by', $this->user->id);
            });
        }

        $tasks->get();

        return DataTables::of($tasks)
            ->addColumn('action', function ($row) {
                $action = '';

                if ($this->user->can('edit_tasks') || ($this->global->task_self == 'yes' && $this->user->id == $row->created_by)) {
                    $action .= '<a href="' . route('member.all-tasks.edit', $row->id) . '" class="btn btn-info btn-circle"
                      data-toggle="tooltip" data-original-title="Edit"><i class="fa fa-pencil" aria-hidden="true"></i></a>';
                }

                if ($this->user->can('delete_tasks')) {
                    $recurringTaskCount = Task::where('recurring_task_id', $row->id)->count();
                    $recurringTask = $recurringTaskCount > 0 ? 'yes' : 'no';

                    $action .= '&nbsp;&nbsp;<a href="javascript:;" class="btn btn-danger btn-circle sa-params"
                      data-toggle="tooltip" data-task-id="' . $row->id . '" data-recurring="' . $recurringTask . '" data-original-title="Delete"><i class="fa fa-times" aria-hidden="true"></i></a>';
                }
                return $action;
            })
            ->editColumn('due_date', function ($row) {
                $now = Carbon::today()->timezone($this->global->timezone);

                if ($row->due_date->lt($now)) {
                    return '<span class="text-danger">' . $row->due_date->format($this->global->date_format) . '</span>';
                } elseif ($row->due_date->eq($now)) {
                    return '<span class="text-success">' . __('app.today') . '</span>';
                }
                return '<span >' . $row->due_date->format($this->global->date_format) . '</span>';
            })
            ->editColumn('created_by', function ($row) {
                if (!is_null($row->created_by)) {
                    return ($row->created_image) ? '<img src="' . asset_url('avatar/' . $row->created_image) . '"
                                                            alt="user" class="img-circle" width="25" height="25"> ' . ucwords($row->created_by) : '<img src="' . asset('img/default-profile-2.png') . '"
                                                            alt="user" class="img-circle" width="25" height="25"> ' . ucwords($row->created_by);
                }
                return '-';
            })
            ->editColumn('users', function ($row) {
                $members = '';
                foreach ($row->users as $member) {
                    $members.= '<a href="' . route('admin.employees.show', [$member->user_id]) . '">';
                    $members .= ($member->user->image) ? '<img data-toggle="tooltip" data-original-title="' . ucwords($member->user->name) . '" src="' . asset_url('avatar/' . $member->user->image) . '"
                    alt="user" class="img-circle" width="25" height="25"> ' : '<img data-toggle="tooltip" data-original-title="' . ucwords($member->user->name) . '" src="' . asset('img/default-profile-2.png') . '"
                    alt="user" class="img-circle" width="25" height="25"> ';
                    $members.= '</a>';
                }
                return $members;
            })
            ->editColumn('heading', function ($row) {
                $name = '<a href="javascript:;" data-task-id="' . $row->id . '" class="show-task-detail">' . ucfirst($row->heading) . '</a>';

                if ($row->is_private) {
                    $name.= ' <i data-toggle="tooltip" data-original-title="' . __('app.private') . '" class="fa fa-lock" style="color: #ea4c89"></i>';
                }
                return $name;
            })
            ->editColumn('board_column', function ($row) {
                return '<label class="label" style="background-color: ' . $row->label_color . '">' . $row->board_column . '</label>';
            })
            ->editColumn('project_name', function ($row) {
                if (is_null($row->project_id)) {
                    return "";
                }
                return '<a href="' . route('member.projects.show', $row->project_id) . '">' . ucfirst($row->project_name) . '</a>';
            })
            ->rawColumns(['board_column', 'action', 'project_name', 'clientName', 'created_by', 'due_date', 'users', 'heading'])
            ->removeColumn('project_id')
            ->removeColumn('image')
            ->removeColumn('label_color')
            ->addIndexColumn()
            ->make(true);
    }

    public function edit($id)
    {
        if (!$this->user->can('edit_tasks') && $this->global->task_self == 'no') {
            abort(403);
        }

        $this->taskBoardColumns = TaskboardColumn::all();
        $this->task = Task::findOrFail($id);

        if (!$this->user->can('add_tasks') && $this->global->task_self == 'yes') {
            $this->projects = Project::join('project_members', 'project_members.project_id', '=', 'projects.id')
                ->join('users', 'users.id', '=', 'project_members.user_id')
                ->where('project_members.user_id', $this->user->id)
                ->select('projects.id', 'projects.project_name')
                ->get();
        } else {
            $this->projects = Project::all();
        }

        $this->employees = User::allEmployees();
        $this->categories = TaskCategory::all();
        $completedTaskColumn = TaskboardColumn::where('slug', '=', 'completed')->first();
        if ($completedTaskColumn) {
            $this->allTasks = Task::join('task_users', 'task_users.task_id', '=', 'tasks.id')->where('board_column_id', '<>', $completedTaskColumn->id)
                ->where('tasks.id', '!=', $id)->select('tasks.*');

            if ($this->task->project_id != '') {
                $this->allTasks = $this->allTasks->where('project_id', $this->task->project_id);
            }

            if (!$this->user->can('view_tasks')) {
                $this->allTasks = $this->allTasks->where('task_users.user_id', '=', $this->user->id);
            }

            $this->allTasks = $this->allTasks->get();
        } else {
            $this->allTasks = [];
        }

        return view('member.all-tasks.edit', $this->data);
    }

    public function update(StoreTask $request, $id)
    {
        $task = Task::findOrFail($id);
        $oldStatus = TaskboardColumn::findOrFail($task->board_column_id);

        $task->heading = $request->heading;
        if ($request->description != '') {
            $task->description = $request->description;
        }
        $task->start_date = Carbon::createFromFormat($this->global->date_format, $request->start_date)->format('Y-m-d');
        $task->due_date = Carbon::createFromFormat($this->global->date_format, $request->due_date)->format('Y-m-d');
        $task->priority = $request->priority;
        $task->board_column_id = $request->status;
        $task->task_category_id = $request->category_id;
        $task->dependent_task_id = $request->has('dependent') && $request->dependent == 'yes' && $request->has('dependent_task_id') && $request->dependent_task_id != '' ? $request->dependent_task_id : null;
        $task->is_private = $request->has('is_private') && $request->is_private == 'true' ? 1 : 0;

        $taskBoardColumn = TaskboardColumn::findOrFail($request->status);
        if ($taskBoardColumn->slug == 'completed') {
            $task->completed_on = Carbon::today()->format('Y-m-d');
        } else {
            $task->completed_on = null;
        }

        $task->project_id = $request->project_id;
        $task->save();

        if (!$this->user->can('add_tasks') && $this->global->task_self == 'yes') {
            $request->user_id = [$this->user->id];
        }

        TaskUser::where('task_id', $task->id)->delete();
        foreach ($request->user_id as $key => $value) {
            TaskUser::create(
                [
                    'user_id' => $value,
                    'task_id' => $task->id
                ]
            );
        }

        $this->logTaskActivity($task->id, $this->user->id, "updateActivity", $task->board_column_id);

        if ($request->project_id) {
            //calculate project progress if enabled
            $this->calculateProjectProgress($request->project_id);
        }
        return Reply::dataOnly(['taskID' => $task->id]);
        //        return Reply::redirect(route('member.all-tasks.index'), __('messages.taskUpdatedSuccessfully'));
    }

    public function destroy(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        // If it is recurring and allowed by user to delete all its recurring tasks
        if ($request->has('recurring') && $request->recurring == 'yes') {
            Task::where('recurring_task_id', $id)->delete();
        }

        Task::destroy($id);

        //calculate project progress if enabled
        $this->calculateProjectProgress($task->project_id);

        return Reply::success(__('messages.taskDeletedSuccessfully'));
    }


    public function create()
    {
        if (!$this->user->can('add_tasks') && $this->global->task_self == 'no') {
            abort(403);
        }

        if (!$this->user->can('add_tasks') && $this->global->task_self == 'yes') {
            $this->projects = Project::join('project_members', 'project_members.project_id', '=', 'projects.id')
                ->join('users', 'users.id', '=', 'project_members.user_id')
                ->where('project_members.user_id', $this->user->id)
                ->select('projects.id', 'projects.project_name')
                ->get();
        } else {
            $this->projects = Project::all();
        }

        $this->employees = User::allEmployees();
        $this->categories = TaskCategory::all();
        $completedTaskColumn = TaskboardColumn::where('slug', '=', 'completed')->first();
        if ($completedTaskColumn) {
            $this->allTasks = Task::join('task_users', 'task_users.task_id', '=', 'tasks.id')->where('board_column_id', '<>', $completedTaskColumn->id)->select('tasks.*');

            if (!$this->user->can('view_tasks')) {
                $this->allTasks = $this->allTasks->where('task_users.user_id', '=', $this->user->id);
            }

            $this->allTasks = $this->allTasks->get();
        } else {
            $this->allTasks = [];
        }

        return view('member.all-tasks.create', $this->data);
    }

    public function membersList($projectId)
    {
        if ($projectId != "all") {
            $this->members = ProjectMember::byProject($projectId);
        } else {
            $this->members = ProjectMember::all();
        }
        $list = view('member.all-tasks.members-list', $this->data)->render();
        return Reply::dataOnly(['html' => $list]);
    }

    public function store(StoreTask $request)
    {
        $task = new Task();
        $task->heading = $request->heading;
        if ($request->description != '') {
            $task->description = $request->description;
        }
        $task->start_date = Carbon::createFromFormat($this->global->date_format, $request->start_date)->format('Y-m-d');
        $task->due_date = Carbon::createFromFormat($this->global->date_format, $request->due_date)->format('Y-m-d');
        $task->project_id = $request->project_id;
        $task->priority = $request->priority;
        $task->board_column_id = $this->global->default_task_status;
        $task->task_category_id = $request->category_id;
        $task->dependent_task_id = $request->has('dependent') && $request->dependent == 'yes' && $request->has('dependent_task_id') && $request->dependent_task_id != '' ? $request->dependent_task_id : null;
        $task->is_private = $request->has('is_private') && $request->is_private == 'true' ? 1 : 0;

        if ($request->board_column_id) {
            $task->board_column_id = $request->board_column_id;
        }

        $task->save();

        if (!$this->user->can('add_tasks') && $this->global->task_self == 'yes') {
            $request->user_id = [$this->user->id];
        }

        foreach ($request->user_id as $key => $value) {
            TaskUser::create(
                [
                    'user_id' => $value,
                    'task_id' => $task->id
                ]
            );
        }

        $this->logTaskActivity($task->id, $this->user->id, "createActivity", $task->board_column_id);

        // For gantt chart
        if ($request->page_name && $request->page_name == 'ganttChart') {
            $parentGanttId = $request->parent_gantt_id;

            $taskDuration = $task->due_date->diffInDays($task->start_date);
            $taskDuration = $taskDuration + 1;

            $ganttTaskArray[] = [
                'id' => $task->id,
                'text' => $task->heading,
                'start_date' => $task->start_date->format('Y-m-d'),
                'duration' => $taskDuration,
                'parent' => $parentGanttId,
                'users' => [
                    ucwords($task->user->name)
                ],
                'taskid' => $task->id
            ];

            $gantTaskLinkArray[] = [
                'id' => 'link_' . $task->id,
                'source' => $task->dependent_task_id != '' ? $task->dependent_task_id : $parentGanttId,
                'target' => $task->id,
                'type' => $task->dependent_task_id != '' ? 0 : 1
            ];
        }

        // Add repeated task
        if ($request->has('repeat') && $request->repeat == 'yes') {
            $repeatCount = $request->repeat_count;
            $repeatType = $request->repeat_type;
            $repeatCycles = $request->repeat_cycles;
            $startDate = Carbon::createFromFormat($this->global->date_format, $request->start_date)->format('Y-m-d');
            $dueDate = Carbon::createFromFormat($this->global->date_format, $request->due_date)->format('Y-m-d');


            for ($i = 1; $i < $repeatCycles; $i++) {
                $repeatStartDate = Carbon::createFromFormat('Y-m-d', $startDate);
                $repeatDueDate = Carbon::createFromFormat('Y-m-d', $dueDate);

                if ($repeatType == 'day') {
                    $repeatStartDate = $repeatStartDate->addDays($repeatCount);
                    $repeatDueDate = $repeatDueDate->addDays($repeatCount);
                } else if ($repeatType == 'week') {
                    $repeatStartDate = $repeatStartDate->addWeeks($repeatCount);
                    $repeatDueDate = $repeatDueDate->addWeeks($repeatCount);
                } else if ($repeatType == 'month') {
                    $repeatStartDate = $repeatStartDate->addMonths($repeatCount);
                    $repeatDueDate = $repeatDueDate->addMonths($repeatCount);
                } else if ($repeatType == 'year') {
                    $repeatStartDate = $repeatStartDate->addYears($repeatCount);
                    $repeatDueDate = $repeatDueDate->addYears($repeatCount);
                }


                $newTask = new Task();
                $newTask->heading = $request->heading;
                if ($request->description != '') {
                    $newTask->description = $request->description;
                }
                $newTask->start_date = $repeatStartDate->format('Y-m-d');
                $newTask->due_date = $repeatDueDate->format('Y-m-d');
                $newTask->project_id = $request->project_id;
                $newTask->priority = $request->priority;
                $newTask->task_category_id = $request->category_id;
                $newTask->recurring_task_id = $task->id;

                if ($request->board_column_id) {
                    $newTask->board_column_id = $request->board_column_id;
                }
  
                $newTask->save();

                if (!$this->user->can('add_tasks') && $this->global->task_self == 'yes') {
                    $request->user_id = [$this->user->id];
                }
        
                foreach ($request->user_id as $key => $value) {
                    TaskUser::create(
                        [
                            'user_id' => $value,
                            'task_id' => $newTask->id
                        ]
                    );
                }

                $startDate = $newTask->start_date->format('Y-m-d');
                $dueDate = $newTask->due_date->format('Y-m-d');
            }
        }

        if ($request->project_id) {
            $this->calculateProjectProgress($request->project_id);
        }

        if (!is_null($request->project_id)) {
            $this->logProjectActivity($request->project_id, __('messages.newTaskAddedToTheProject'));
        }

        //log search
        $this->logSearchEntry($task->id, 'Task ' . $task->heading, 'admin.all-tasks.edit', 'task');

        if ($request->board_column_id) {
            return Reply::redirect(route('member.taskboard.index'), __('messages.taskCreatedSuccessfully'));
        }

        return Reply::dataOnly(['taskID' => $task->id]);

        //        return Reply::redirect(route('member.all-tasks.index'), __('messages.taskCreatedSuccessfully'));
    }

    public function showFiles($id)
    {
        $this->taskFiles = TaskFile::where('task_id', $id)->get();
        return view('member.all-tasks.ajax-file-list', $this->data);
    }

    public function ajaxCreate($columnId)
    {
        $this->projects = Project::all();
        $this->columnId = $columnId;
        $this->employees = User::allEmployees();
        $completedTaskColumn = TaskboardColumn::where('slug', '!=', 'completed')->first();
        if ($completedTaskColumn) {
            $this->allTasks = Task::join('task_users', 'task_users.task_id', '=', 'tasks.id')->where('board_column_id', $completedTaskColumn->id);

            if (!$this->user->can('view_tasks')) {
                $this->allTasks = $this->allTasks->where('task_users.user_id', '=', $this->user->id);
            }

            $this->allTasks = $this->allTasks->get();
        } else {
            $this->allTasks = [];
        }

        return view('member.all-tasks.ajax_create', $this->data);
    }

    public function remindForTask($taskID)
    {
        $task = Task::with('users')->findOrFail($taskID);

        // Send  reminder notification to user
        $userIds = $task->users->pluck('user_id')->toArray();
        $taskUsers = User::whereIn('id', $userIds)->get();
        Notification::send($taskUsers, new TaskReminder($task));

        return Reply::success('messages.reminderMailSuccess');
    }

    public function show($id)
    {
        //        dd('test');
        $this->task = Task::with('board_column', 'subtasks', 'project', 'users', 'users.user')->findOrFail($id);
        $view = view('member.all-tasks.show', $this->data)->render();
        return Reply::dataOnly(['status' => 'success', 'view' => $view]);
    }

    public function updateTaskDuration(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $task->start_date = Carbon::createFromFormat('d/m/Y', $request->start_date)->format('Y-m-d');
        $task->due_date = Carbon::createFromFormat('d/m/Y', $request->end_date)->format('Y-m-d');
        $task->save();

        $this->logTaskActivity($task->id, $this->user->id, "updateActivity", $task->board_column_id);

        return Reply::success('messages.taskUpdatedSuccessfully');
    }

    public function dependentTaskLists($projectId, $taskId = null)
    {
        $completedTaskColumn = TaskboardColumn::where('slug', '!=', 'completed')->first();
        if ($completedTaskColumn) {
            $this->allTasks = Task::join('task_users', 'task_users.task_id', '=', 'tasks.id')->where('board_column_id', $completedTaskColumn->id)
                ->where('project_id', $projectId);

            if ($taskId != null) {
                $this->allTasks = $this->allTasks->where('id', '!=', $taskId);
            }

            if (!$this->user->can('view_tasks')) {
                $this->allTasks = $this->allTasks->where('task_users.user_id', '=', $this->user->id);
            }

            $this->allTasks = $this->allTasks->get();
        } else {
            $this->allTasks = [];
        }

        $list = view('member.tasks.dependent-task-list', $this->data)->render();
        return Reply::dataOnly(['html' => $list]);
    }

    public function history($id)
    {
        $this->task = Task::with('board_column', 'history', 'history.board_column')->findOrFail($id);
        $view = view('admin.tasks.history', $this->data)->render();
        return Reply::dataOnly(['status' => 'success', 'view' => $view]);
    }
}