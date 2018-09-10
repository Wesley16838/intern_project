<?php defined('BASEPATH') OR exit('No direct script access allowed');

// controllers 名稱 // 須修改
class Subject extends Admin_Controller
{
  // 基本設定
  public $table = 'subject_tb'; // 資料庫table name // 須修改
  protected $jointable = 'subject_xmail_tb'; // 須修改

  /***********
    列表頁
  ***********/
  // 表格-呈現欄位 // 須修改
  public $tableTitle = array('刊登','主旨');
  public $tableColumn = array('xpublish','xsubject');
  public $tableHeadSize = array('8%','70%');
  public $tableSortValue = array(true,true);
  // 表格-縮圖設定 // 須修改
  protected $imageSize = 'original'; // original、mid、small
  protected $imageArray = array('xfile1');
  // 表格-搜尋欄位 // 須修改
  public $searchTitle = array('主標');
  public $searchColumn = array('xsubject');
  // 表格-其他功能 // 須修改
  public $showPaging = true;
  public $showSearching = false;
  public $showMulti = true;
  // 其他頁面
  public $otherPage = true;

  function __construct()
  {
    parent::__construct();

    $this->is_logged_in(); // 判斷是否登入

    // 頁面設定 // 固定
    $this->maintable = $this->session->userdata('preMainTable');
    $showother = (strpos(current_url(),$this->indexPath)>-1)?false:true;
    if($this->session->userdata('otherPageUrl') && $showother) {
      $this->indexPath = $this->session->userdata('otherPageUrl');
      $this->viewPath = $this->indexPath.'_view'; // 首頁 // 固定
      $this->formPath = $this->indexPath.'/form'; // 新增頁 // 固定
    }
    if($this->session->userdata('preMenuID')!='') {
      $perid = $this->session->userdata('preid');
    } else $perid = '';
    // 麵包屑
    $this->subnavPath = $this->common->getsubnav($perid); // 固定

    // 頁碼設定
    if(!$this->session->userdata('pageNumber')) {
      $this->session->set_userdata('pageNumber', 1);
    }
    $this->currentPage = $this->session->userdata('pageNumber');
  }

  public function index()
  {
    $data = array();

    $this->data['action'] = 'index';

    $layout['content'] = $this->load->view($this->viewPath, $this->data, true);
    $this->load->view($this->adminview, $layout);
  }

  // 讀取列表
  public function read($id = NULL)
  {
    $actionIndex = false; // 是否在列表頁
    // 排序:置入某筆資料
    if($id) $data['data'] = $this->admin_crud->result_array($this->admin_crud->query_where($this->table, array('pid !='=>$id), true, 'xsort', 'asc'));
    else {
      $data['data'] = $this->admin_crud->read('*', $this->table, 'xsort', 'asc');
      $actionIndex = true;
    }
    // 處理列表中圖片
    if(count($data['data']) > 0 && $actionIndex && count($this->imageArray) > 0) { $count = -1;
      foreach ($data['data'] as $value) { $count++;
        for ($i=0;$i<count($this->imageArray);$i++) {
          if(isset($data['data'][$count][$this->imageArray[$i]])) {
            $data['data'][$count][$this->imageArray[$i]] = $this->common->getImagethumb($this->table, $this->imageArray[$i], $this->imageSize, $value[$this->imageArray[$i]]);
          }
        }
      }
    }
    return $this->output->set_content_type('application/json')->set_output(json_encode($data));
  }

  // 新增/更新介面表單
  public function form($id = NULL)
  {
    // 更新介面
    if(isset($id)) {

      $this->data['action'] = 'update';

      // 讀取列表
      $array = $this->admin_crud->result_array($this->admin_crud->query_where($this->table, array('pid'=>$id)));
      $this->data['list'] = $array[0];
      $this->data['list']['xmail'] = $this->admin_crud->result_array($this->admin_crud->query_where($this->jointable, array('fsubjectpid'=>$id)));;

    } else {

      $this->data['action'] = 'create';
    }

    $layout['content'] = $this->load->view($this->viewPath, $this->data, true);
    $this->load->view($this->adminview, $layout);
  }

  // 新增/更新
  public function save($id = NULL)
  {
    if(
      $this->input->post('xsubject', true)
    ) {
        // 排序選項處理
        $xsort = $this->common->processSort($this->table,$this->input->post('xsort', true),$this->input->post('insertxsortpid', true));

        // DB資料
        $data = array(
          'xpublish'=> ($this->input->post('xpublish', true)) ? $this->input->post('xpublish', true) : '',
          'xsubject'=> $this->input->post('xsubject', true),
          'xmailsubject'=> ($this->input->post('xmailsubject', true)) ? $this->input->post('xmailsubject', true) : '',
          ($id) ? 'xmodify' : 'xcreate'=> date('Y-m-d H:i:s'),
          'xsort'=> $xsort,
        );

        // 新增、更新動作
        if($id) {
          $actionMessage = '修改';
          $this->admin_crud->update($this->table, $id, $data);
        }
        else {
          $actionMessage = '新增';
          $id = $this->admin_crud->create($this->table, $data);
        }
        $this->track->trackingDoing($this->table,'xsubject',$this->data['permission']['xname'],$actionMessage,$id,$this->data['selfaccount'],$this->data['selflevel']);

        // xmail處理
        $array = $this->admin_crud->result_array($this->admin_crud->query_where($this->jointable, array('fsubjectpid'=>$id)));
        // 取得舊/新資料
        if(count($array) > 0) {
          foreach ($array as $value) { $old[] = $value['xmail']; }
        } else $old = array();
        $postArr = $this->input->post('xmail[]', true);
        $new = (count($postArr)>0)?array_filter($postArr):array();
        // 比較陣列
        if(count($new) > 0 && count($old) > 0) {
          $add = array_diff($new,$old); // 新增
          $remove = array_diff($old,$new); // 減少
        } else if(count($new) == 0 && count($old) > 0) {
          $add = array();
          $remove = $old;
        } else {
          $add = $new;
          $remove = array();
        }
        // 新增項目
        if(count($add) > 0) {
          foreach ($add as $value) {
            $this->admin_crud->create($this->jointable, array(
              'fsubjectpid'=> $id,
              'xmail'=> $value,
              'xcreate'=> date('Y-m-d H:i:s'),
            ));
          }
        }
        // 刪除項目
        if(count($remove) > 0) {
          foreach ($remove as $value) {
            $this->admin_crud->delete($this->jointable, array(
              'fsubjectpid'=>$id,
              'xmail'=> $value,
            ));
          }
        }
    } else {
      $data['error'] = '欄位未輸入';
    }
    $this->output->set_content_type('application/json')->set_output(json_encode($data));
  }

  // 批次
  public function batchItem($action)
  {
    // 勾選的列表項目(pid)
    $array = $this->input->post('data', true);

    if(count($array) > 0) {

      $now = date('Y-m-d H:i:s');

      switch ($action) {
        case 'delete':
          $data['success'] = '刪除';
          foreach ($array as $value) {
            $this->track->trackingDoing($this->table,'xsubject',$this->data['permission']['xname'],$data['success'],$value,$this->data['selfaccount'],$this->data['selflevel']);
            $this->admin_crud->delete($this->table, array('pid'=>$value));
            // 刪除關聯
            $this->admin_crud->delete($this->jointable, array('fsubjectpid'=>$value));
          }
          break;
        case 'release':
          $data['success'] = '刊登';
          foreach ($array as $value) {
            $this->admin_crud->update($this->table, $value, array(
              'xpublish'=> 'yes',
              'xmodify'=> $now
            ));
            $this->track->trackingDoing($this->table,'xsubject',$this->data['permission']['xname'],$data['success'],$value,$this->data['selfaccount'],$this->data['selflevel']);
          }
          break;
        case '_release':
          $data['success'] = '取消刊登';
          foreach ($array as $value) {
            $this->admin_crud->update($this->table, $value, array(
              'xpublish'=> 'no',
              'xmodify'=> $now
            ));
            $this->track->trackingDoing($this->table,'xsubject',$this->data['permission']['xname'],$data['success'],$value,$this->data['selfaccount'],$this->data['selflevel']);
          }
          break;
        // case 'home':
        //   $data['success'] = '刊登首頁';
        //   foreach ($array as $value) {
        //     $this->admin_crud->update($this->table, $value, array(
        //       'xindex'=> 'yes',
        //       'xmodify'=> $now
        //     ));
        //     $this->track->trackingDoing($this->table,'xsubject',$this->data['permission']['xname'],$data['success'],$value,$this->data['selfaccount'],$this->data['selflevel']);
        //   }
        //   break;
        // case '_home':
        //   $data['success'] = '取消刊登首頁';
        //   foreach ($array as $value) {
        //     $this->admin_crud->update($this->table, $value, array(
        //       'xindex'=> 'no',
        //       'xmodify'=> $now
        //     ));
        //     $this->track->trackingDoing($this->table,'xsubject',$this->data['permission']['xname'],$data['success'],$value,$this->data['selfaccount'],$this->data['selflevel']);
        //   }
        //   break;
        default:
          $data = array();
          break;
      }
    } else {
      $data['error'] = '請選擇項目';
    }
    $this->output->set_content_type('application/json')->set_output(json_encode($data));
  }

  // 拖曳排序
  public function sort()
  {
    $obj = json_decode($this->input->post('data', true));
    $start = $this->input->post('start', true);

    foreach ($obj[0] as $key => $value) {
      $this->admin_crud->update($this->table, $value->id, array('xsort' => $start+$key+1));
    }
  }

  // 紀錄目前頁面
  public function recordPage()
  {
    $this->session->set_userdata('pageNumber', $this->input->post('page', true));
  }
}

// application/controllers/