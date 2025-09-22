<?php
class Reports {
    public static function ledger(int $accountId,?string $start,?string $end): array {
        if(!Meta::tableExists('journal_lines')||!Meta::tableExists('journals')) Util::err("Ledger tables missing");
        $pdo=db();
        $p=['aid'=>$accountId]; $f='';
        if($start){ $f.=" AND j.voucher_date>=:ds"; $p['ds']=$start; }
        if($end){ $f.=" AND j.voucher_date<=:de"; $p['de']=$end; }
        $sql="SELECT j.id journal_id,j.voucher_no,j.voucher_date,j.narration,l.debit,l.credit,l.description
              FROM journal_lines l
              JOIN journals j ON j.id=l.journal_id
              WHERE l.account_id=:aid $f
              ORDER BY j.voucher_date,j.id,l.id";
        $st=$pdo->prepare($sql); $st->execute($p);
        $rows=$st->fetchAll();
        $run=0; foreach($rows as &$r){ $run+= (float)$r['debit']-(float)$r['credit']; $r['balance']=$run; }
        return $rows;
    }
    public static function trialBalance(?string $endDate=null): array {
        if(!Meta::tableExists('accounts')||!Meta::tableExists('journal_lines')||!Meta::tableExists('journals'))
            Util::err("Trial balance tables missing");
        $pdo=db(); $p=[]; $f=$endDate?" AND j.voucher_date<=:d":"";
        if($endDate) $p['d']=$endDate;
        $sql="SELECT a.id,a.code,a.name,a.account_type,
                     SUM(l.debit) debit_sum,SUM(l.credit) credit_sum
              FROM accounts a
              LEFT JOIN journal_lines l ON l.account_id=a.id
              LEFT JOIN journals j ON j.id=l.journal_id
              WHERE 1=1 $f
              GROUP BY a.id,a.code,a.name,a.account_type
              ORDER BY a.code";
        $st=$pdo->prepare($sql); $st->execute($p);
        $rows=$st->fetchAll();
        $td=$tc=0;
        foreach($rows as &$r){
            $r['debit_sum']=(float)$r['debit_sum'];
            $r['credit_sum']=(float)$r['credit_sum'];
            $td+=$r['debit_sum']; $tc+=$r['credit_sum'];
        }
        return ['accounts'=>$rows,'total_debit'=>$td,'total_credit'=>$tc,'balanced'=>round($td,2)===round($tc,2)];
    }
    public static function gstSummary(?string $start,?string $end): array {
        if(!Meta::tableExists('invoices')||!Meta::tableExists('invoice_items')) Util::err("GST tables missing");
        $pdo=db(); $p=[]; $w="WHERE 1=1";
        if($start){ $w.=" AND i.invoice_date>=:s"; $p['s']=$start; }
        if($end){ $w.=" AND i.invoice_date<=:e"; $p['e']=$end; }

        $itemCols=array_column(Meta::columns('invoice_items'),'Field');
        // Try flexible naming:
        $taxableCol = in_array('taxable_value',$itemCols)?'taxable_value':(in_array('line_total',$itemCols)?'line_total':null);
        if(!$taxableCol) Util::err("Cannot determine taxable base column");
        $hasTaxRate=in_array('tax_rate',$itemCols);
        $hasHSN=in_array('hsn_code',$itemCols);
        $hasCGST=in_array('cgst',$itemCols);
        $hasSGST=in_array('sgst',$itemCols);
        $hasIGST=in_array('igst',$itemCols);
        $hasTaxAmount=in_array('tax_amount',$itemCols);

        $sel=["SUM(it.$taxableCol) taxable_sum"];
        if($hasTaxRate) $sel[]="it.tax_rate";
        if($hasHSN) $sel[]="it.hsn_code";
        if($hasCGST) $sel[]="SUM(it.cgst) cgst_sum";
        if($hasSGST) $sel[]="SUM(it.sgst) sgst_sum";
        if($hasIGST) $sel[]="SUM(it.igst) igst_sum";
        if(!$hasCGST && !$hasSGST && !$hasIGST && $hasTaxAmount) $sel[]="SUM(it.tax_amount) tax_sum";

        $group=[];
        if($hasHSN) $group[]="it.hsn_code";
        if($hasTaxRate)$group[]="it.tax_rate";

        $sql="SELECT ".implode(',',$sel)." FROM invoice_items it JOIN invoices i ON i.id=it.invoice_id $w";
        if($group) $sql.=" GROUP BY ".implode(',',$group)." ORDER BY ".implode(',',$group);

        $st=$pdo->prepare($sql); $st->execute($p);
        return ['rows'=>$st->fetchAll(),'grouping'=>$group,'basis'=>$taxableCol];
    }
}
