<?php
class Posting {
    public static function post(array $header,array $lines): array {
        if(!Meta::tableExists('journals') || !Meta::tableExists('journal_lines')){
            Util::err("Journal tables missing");
        }
        Validation::requireKeys($header,['voucher_no','voucher_date']);
        if(!$lines) Util::err("No lines");
        $date=Util::ymd($header['voucher_date']); if(!$date) Util::err("Invalid voucher_date");
        $td=0; $tc=0;
        foreach($lines as $i=>$ln){
            Validation::requireKeys($ln,['account_id']);
            $d=Util::num($ln['debit']??0); $c=Util::num($ln['credit']??0);
            if($d>0 && $c>0) Util::err("Line $i both debit and credit");
            if($d==0 && $c==0) Util::err("Line $i missing amount");
            $td+=$d; $tc+=$c;
        }
        if(round($td,2)!==round($tc,2)) Util::err("Unbalanced: $td vs $tc");
        $pdo=db(); $pdo->beginTransaction();
        try{
            $pdo->prepare("INSERT INTO journals (voucher_no,voucher_date,narration,created_at) VALUES (?,?,?,NOW())")
                ->execute([$header['voucher_no'],$date,$header['narration']??null]);
            $jid=$pdo->lastInsertId();
            $ins=$pdo->prepare("INSERT INTO journal_lines (journal_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)");
            foreach($lines as $ln){
                $ins->execute([$jid,$ln['account_id'],Util::num($ln['debit']??0),Util::num($ln['credit']??0),$ln['description']??null]);
            }
            $pdo->commit();
            return ['journal_id'=>$jid,'total_debit'=>$td,'total_credit'=>$tc];
        }catch(Throwable $e){
            $pdo->rollBack(); Util::err("Post failed: ".$e->getMessage(),500);
        }
    }
}
