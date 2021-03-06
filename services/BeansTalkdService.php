<?php
/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 16-11-30
 * Time: 下午2:19
 */

use Pheanstalk\Pheanstalk;

class BeanstalkdService
{
    public function __construct()
    {
        $host = '127.0.0.1';
        $port = 11300;
        $this->con = new Pheanstalk($host, $port);
    }

    /**
     * 获取tubes列表
     * @return array
     */
    public function getTubeList()
    {
        $tubes = $this->con->listTubes();
        $arr = [];
        $i = 0;
        foreach ($tubes as $key => $val) {
            $arr[$i] = $this->getTubeDetail($val);
            $i++;
        }
        return $arr;
    }

    /**
     * 获取tube详情
     */
    public function getTubeDetail($tubename)
    {
        $stats = $this->con->statsTube($tubename);
        $tube['name'] = $stats->name;
        $tube['urgent'] = $stats->current_jobs_urgent;
        $tube['ready'] = $stats->current_jobs_ready;
        $tube['reserved'] = $stats->current_jobs_reserved;
        $tube['delayed'] = $stats->current_jobs_delayed;
        $tube['buried'] = $stats->current_jobs_buried;
        $tube['total'] = $stats->total_jobs;
        $tube['delete'] = $stats->cmd_delete;
        return $tube;
    }

    /**
     *
     * @param $tubesArr
     * @return string
     */
    public function tubsArrToHtml($tubesArr)
    {
        $html='<div class="in" style="margin-bottom: 5px;">共'.count($tubesArr).'个消息队列</div>   
                <div class="table-scrollable">
                    <table class="table table-hover table-striped table-bordered">
                        <thead class="bordered-blue">
                            <tr>
                                <th>通道名称</th>
                                <th>优先执行</th>
                                <th>准备执行</th>
                                <th>执行中</th>
                                <th>延时执行</th>
                                <th>休眠中</th>
                                <th>总任务数</th>
                                <th>命令删除数量</th>
                                <th>操作</th>
                            </tr>
                    </thead>
                    <tbody>';
                    foreach($tubesArr as $v) {
                        $tdd = <<<"TDD"
                            <tr>
                            <td>{$v['name']}</td>
                            <td>{$v['urgent']}</td>
                            <td>{$v['ready']}</td>
                            <td>{$v['reserved']}</td>
                            <td>{$v['delayed']}</td>
                            <td>{$v['buried']}</td>
                            <td>{$v['total']}</td>
                            <td>{$v['delete']}</td>
                            <td>
                              <a class="btn btn-success" href="javascript:tubeInfo('{$v['name']}')">查看</a>
                            </td>
                        </tr>
TDD;
                        $html .= $tdd;
                    }
        $html.='</tbody></table></div>';

        return $html;
    }

    /**
     * 跟据tube 各状态数量获取最新一条记录
     */
    public function getTubeData($tubedetail)
    {
        $data = [];
        if ($tubedetail['ready'] > 0) {
            $datas = $this->con->peekReady($tubedetail['name']);
            $datas_id = $datas->getId();
            $status = $this->getJobStatus($datas_id);
            $datas_data = json_decode($datas->getData(), true);
            $data[] = ['action' => 'ready',
                'action_name' => '准备执行',
                'job_id' => $datas_id,
                'job_data' => $datas_data,
                'status' => $status
            ];
        }
        if ($tubedetail['delayed'] > 0) {
            $datas = $this->con->peekDelayed($tubedetail['name']);
            $datas_id = $datas->getId();
            $status = $this->getJobStatus($datas_id);
            $datas_data = json_decode($datas->getData(), true);
            $data[] = ['action' => 'ready', 'action_name' => '准备执行', 'job_id' => $datas_id, 'job_data' => $datas_data, 'status' => $status];
        }
        if ($tubedetail['buried'] > 0) {
            $datas = $this->con->peekBuried($tubedetail['name']);
            $datas_id = $datas->getId();
            $status = $this->getJobStatus($datas_id);
            $datas_data = json_decode($datas->getData(), true);
            $data[] = ['action' => 'ready', 'action_name' => '准备执行', 'job_id' => $datas_id, 'job_data' => $datas_data, 'status' => $status];
        }
        return $data;
    }

    /**
     * 根据jobid获取数据
     */
    public function getJobData($jobid)
    {
        $jobdata = $this->con->peek($jobid);
        $datas_data = json_decode($jobdata->getData(), true);
        return $datas_data;
    }

    /**
     * 根据jobid获取job 状态
     */
    public function getJobStatus($jobid)
    {
        $datas = $this->con->statsJob($jobid);
        $data['id'] = $datas->id;
        $data['tube'] = $datas->tube;
        $data['state'] = $datas->state;
        $data['pri'] = $datas->pri;
        $data['age'] = $datas->age;
        $data['delay'] = $datas->delay;
        $data['ttr'] = $datas->ttr;
        $data['time_left'] = $datas->time_left;
        $data['reserves'] = $datas->reserves; //被执行次数
        $data['timeouts'] = $datas->timeouts;//超时次数
        $data['releases'] = $datas->releases;
        $data['buries'] = $datas->buries;  //休眠次数
        $data['kicks'] = $datas->kicks;  //激活次数
        return $data;
    }

    /**
     * 删除job
     */
    public function delJob($jobid)
    {
        $job = $this->con->peek($jobid);
        $this->con->delete($job);
    }

    /**
     * 休眠job
     */
    public function buryJob($tube)
    {
        $job = $this->con->reserveFromTube($tube, 3);
        $this->con->bury($job);
    }

    /**
     * 唤醒job
     */
    public function kickJob($jobid)
    {
        $job = $this->con->peek($jobid);
        $this->con->kickJob($job);
    }

    /**
     * 取出一个任务
     */
    public function reserveJob()
    {
        $job = $this->con->reserveFromTube('default', 1);
        sleep(2);
        $this->con->release($job, 1);
        sleep(20);
        var_dump($job);
    }

    /**
     * 删掉一个管道
     */
    public function delTube($tube)
    {
        $this->con->ignore($tube);
    }
}

