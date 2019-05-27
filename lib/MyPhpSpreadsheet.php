<?php

/**
 * MyPhpSpreadsheet类
 */

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
//use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as ReaderXlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
//use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

error_reporting(E_ALL);

class MyPhpSpreadsheet{

    /**
     * 导出excel
     */
    public function arrayToXlsx($data,$title){

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        //设置工作表标题名称
        $worksheet->setTitle('工作表格1');

        //表头 设置单元格内容
        foreach ($title as $key => $value) {
            $worksheet->setCellValueByColumnAndRow($key+1, 1, $value);
        }

        $row = 2; //从第二行开始
        foreach ($data as $item) {
            $column = 1; //从第一列设置并初始化
            foreach ($item as $value) {
                $worksheet->setCellValueByColumnAndRow($column, $row, $value); //哪一列哪一行设置哪个值
                $column++; //列数加1
            }
            $row++; //行数加1
        }

        $fileName = date('YmdHis',time());
        $fileType = 'Xlsx';
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $this->excelBrowserExport($fileName, $fileType);
//        $writer->save('php://output');
        //保存到upload文件夹
        $writer->save('../upload/'.$fileName.'.'.$fileType);
        return [
            'filename' => $fileName.'.'.$fileType,
            'filedir' => '../upload/'.$fileName.'.'.$fileType,
            'url'=> '/upload/'.$fileName.'.'.$fileType
        ];
    }

    public function excelBrowserExport($fileName, $fileType) {
        //文件名称校验
        if(!$fileName) {
            throw new Exception('文件名不能为空');
        }

        //Excel文件类型校验
        $type = ['Excel2007', 'Xlsx', 'Excel5', 'xls'];
        if(!in_array($fileType, $type)) {
            throw new Exception('未知文件类型');
        }

        if($fileType == 'Excel2007' || $fileType == 'Xlsx') {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="'.$fileName.'.xlsx"');
            header('Cache-Control: max-age=0');
        } else {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="'.$fileName.'.xls"');
            header('Cache-Control: max-age=0');
        }
    }


    /**
     * 读取xlsx（建议）
     * @param 上传的临时文件地址
     */
    public function read_xlsx($files){
        $tmp = $files['tmp_name'];
        if(empty($tmp)){
            echo '请选择要导入的Xlsx文件！';exit;
        }

        $reader = new ReaderXlsx();
        $spreadsheet = $reader->load($tmp);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        var_dump($sheetData);exit;
        if(!empty($sheetData)){
            foreach($sheetData as $k=>$v){
                $id = $v['A'];
                $portal_code = $v['B'];
                $store_name = $v['C'];
                if(empty($id)){
                    break;
                }
            }
        }
        var_dump($sheetData);exit;
    }



}
?>