<?php
class Invoices {
    public static function list(array $opts=[]): array {
        if(!Meta::tableExists('invoices')) Util::err("Invoices table missing");
        $pdo=db(); $p=[]; $w="WHERE 1=1";
        if(!empty($opts['from'])){ $w.=" AND invoice_date>=:f"; $p['f']=$opts['from']; }
        if(!empty($opts['to'])){ $w.=" AND invoice_date<=:t"; $p['t']=$opts['to']; }
        $sql="SELECT * FROM invoices $w ORDER BY invoice_date DESC,id DESC LIMIT 500";
        $st=$pdo->prepare($sql); $st->execute($p);
        return $st->fetchAll();
    }

    public static function create(array $header,array $items): array {
        if(!Meta::tableExists('invoices')||!Meta::tableExists('invoice_items')) Util::err("Invoice tables missing");
        Validation::requireKeys($header,['invoice_number','invoice_date']);
        if(!$items) Util::err("No items");
        $date=Util::ymd($header['invoice_date']); if(!$date) Util::err("Bad invoice_date");
        $pdo=db(); $pdo->beginTransaction();
        try{
            $pdo->prepare("INSERT INTO invoices (invoice_number,invoice_date,narration,created_at) VALUES (?,?,?,NOW())")
                ->execute([$header['invoice_number'],$date,$header['narration']??null]);
            $iid=$pdo->lastInsertId();
            $itemCols=array_column(Meta::columns('invoice_items'),'Field');
            $hasQty=in_array('qty',$itemCols);
            $hasRate=in_array('rate',$itemCols);
            $hasTaxable=in_array('taxable_value',$itemCols);
            $hasTaxRate=in_array('tax_rate',$itemCols);
            $hasHSN=in_array('hsn_code',$itemCols);
            $hasCGST=in_array('cgst',$itemCols);
            $hasSGST=in_array('sgst',$itemCols);
            $hasIGST=in_array('igst',$itemCols);
            $hasLineTotal=in_array('line_total',$itemCols);
            $hasTaxAmount=in_array('tax_amount',$itemCols);

            $cols=['invoice_id','description'];
            if($hasQty)$cols[]='qty';
            if($hasRate)$cols[]='rate';
            if($hasTaxable)$cols[]='taxable_value';
            if($hasTaxRate)$cols[]='tax_rate';
            if($hasHSN)$cols[]='hsn_code';
            if($hasCGST)$cols[]='cgst';
            if($hasSGST)$cols[]='sgst';
            if($hasIGST)$cols[]='igst';
            if($hasLineTotal)$cols[]='line_total';
            if($hasTaxAmount)$cols[]='tax_amount';

            $ph='('.implode(',',array_fill(0,count($cols),'?')).')';
            $ins=$pdo->prepare("INSERT INTO invoice_items (".implode(',',$cols).") VALUES $ph");

            $totalTaxable=0; $totalTax=0;
            foreach($items as $it){
                $desc=$it['description']??'';
                $qty=$hasQty?(float)($it['qty']??1):1;
                $rate=$hasRate?(float)($it['rate']??0):0;
                $taxable=$hasTaxable?(float)($it['taxable_value']??($qty*$rate)):$qty*$rate;
                $taxRate=$hasTaxRate?(float)($it['tax_rate']??0):0;
                $cgst=$hasCGST?(float)($it['cgst']??0):0;
                $sgst=$hasSGST?(float)($it['sgst']??0):0;
                $igst=$hasIGST?(float)($it['igst']??0):0;
                $taxAmount=0;
                if($hasTaxAmount){
                    if(isset($it['tax_amount'])) $taxAmount=(float)$it['tax_amount'];
                    else if($hasCGST||$hasSGST||$hasIGST) $taxAmount=$cgst+$sgst+$igst;
                    else if($hasTaxRate) $taxAmount=round($taxable*$taxRate/100,2);
                }
                $lineTotal = $taxable + ($taxAmount?:($cgst+$sgst+$igst));

                $row=[$iid,$desc];
                if($hasQty)$row[]=$qty;
                if($hasRate)$row[]=$rate;
                if($hasTaxable)$row[]=$taxable;
                if($hasTaxRate)$row[]=$taxRate;
                if($hasHSN)$row[]=$it['hsn_code']??null;
                if($hasCGST)$row[]=$cgst;
                if($hasSGST)$row[]=$sgst;
                if($hasIGST)$row[]=$igst;
                if($hasLineTotal)$row[]=$lineTotal;
                if($hasTaxAmount)$row[]=$taxAmount;
                $ins->execute($row);

                $totalTaxable+=$taxable;
                $totalTax+= $taxAmount?:($cgst+$sgst+$igst);
            }

            // Aggregate update if columns exist
            $invCols=array_column(Meta::columns('invoices'),'Field');
            $parts=[];$bind=[]; 
            if(in_array('total_taxable',$invCols)){$parts[]="total_taxable=?";$bind[]=$totalTaxable;}
            if(in_array('total_tax',$invCols)){$parts[]="total_tax=?";$bind[]=$totalTax;}
            if(in_array('grand_total',$invCols)){$parts[]="grand_total=?";$bind[]=$totalTaxable+$totalTax;}
            if($parts){
                $bind[]=$iid;
                $pdo->prepare("UPDATE invoices SET ".implode(',',$parts)." WHERE id=? LIMIT 1")->execute($bind);
            }
            $pdo->commit();
            return ['invoice_id'=>$iid,'total_taxable'=>$totalTaxable,'total_tax'=>$totalTax,'grand_total'=>$totalTaxable+$totalTax];
        }catch(Throwable $e){
            $pdo->rollBack(); Util::err("Invoice create failed: ".$e->getMessage(),500);
        }
    }

    public static function view(int $id): array {
        if(!Meta::tableExists('invoices')||!Meta::tableExists('invoice_items')) Util::err("Invoice tables missing");
        $pdo=db();
        $h=$pdo->prepare("SELECT * FROM invoices WHERE id=?"); $h->execute([$id]);
        $head=$h->fetch(); if(!$head) Util::err("Not found",404);
        $it=$pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id"); $it->execute([$id]);
        return ['invoice'=>$head,'items'=>$it->fetchAll()];
    }
}
