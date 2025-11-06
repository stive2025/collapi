<?php

namespace App\Services;

use App\Models\User;

class UtilsService
{
    /**
     * Create a new class instance.
     */
    public function __construct(){}
    
    public function setState(string $value){
        if(
            $value=='PREJUDICIAL' | 
            $value=='EN TRAMITE JUDICIAL' | 
            $value=='VENCIDO TOTAL' | 
            $value=='Cartera Vendida' | 
            $value=='Vencido'
        ){
            return 'Vencido';
        }else if(
            $value=='CANCELADO' | 
            $value=='Cancelado'
        ){
            return 'Cancelado';
        }else if($value=='JUDICIAL'){
            return 'Judicial';
        }else if(
            $value=='VIGENTE' | 
            $value=="Vigente"
        ){
            return 'Vigente';
        }else if($value=="Castigado"){
            return 'Castigado';
        }else if($value=='CONVENIO DE PAGO'){
            return 'CONVENIO DE PAGO';
        }
    }

    public function rewriteValue(string $value){
        if($value>0){
            return bcdiv($value,'1',2);
        }else{
            return strval("0");
        }
    }
    
    public function setRango(int $dias_mora){
        if($dias_mora<2){
            return "A) Preventiva";
        }else if($dias_mora>=2 & $dias_mora<=5){
            return "B) 2-5";
        }else if($dias_mora>=6 & $dias_mora<=15){
            return "C) 6-15";
        }else if($dias_mora>=16 & $dias_mora<=30){
            return "D) 16-30";
        }else if($dias_mora>=31 & $dias_mora<=60){
            return "E) 31-60";
        }else if($dias_mora>=61 & $dias_mora<=90){
            return "F) 61-90";
        }else if($dias_mora>=91 & $dias_mora<=120){
            return "G) 91-120";
        }else if($dias_mora>=121 & $dias_mora<=180){
            return "H) 121-180";
        }else if($dias_mora>=181 & $dias_mora<=360){
            return "I) 181-360";
        }else if($dias_mora>=361 & $dias_mora<=720){
            return "J) 361-720";
        }else if($dias_mora>=721 & $dias_mora<=1080){
            return "K) 721-1080";
        }else if($dias_mora>1081){
            return "L) Más de 1080";
        }
    }

    public function setNameUser(int $user_id){
        $user=User::where('id',$user_id)->first();
        return $user->name;
    }

    public function exits($credit_id,$reports,$fecha_pago){
        $data_dia_sincronizado=[];
        $active=false;
        $inactive=false;
        
        foreach($reports as $report){
            if(json_decode($report->imagen_creditos)!=null){
                foreach(json_decode($report->imagen_creditos) as $credito){
                    if($fecha_pago==$report->fecha & intval($credit_id)==intval($credito->credito)){
                        $data_dia_sincronizado=[
                            "credito"=>$credit_id,
                            "dias_mora"=>$credito->dias_mora,
                            "monto"=>$credito->monto,
                            "saldo_capital"=>$credito->saldo_capital,
                            "interes"=>$credito->interes,
                            "mora"=>$credito->mora,
                            "seguro_desgravamen"=>$credito->seguro_desgravamen,
                            "gastos_cobranza"=>$credito->gastos_cobranza,
                            "gastos_judiciales"=>$credito->gastos_judiciales,
                            "otros"=>$credito->otros,
                            "rango"=>$credito->rango,
                            "con_gestion"=>$credito->con_gestion,
                            "agente"=>$credito->agente,
                            "fecha"=>$report->fecha
                        ];
                        
                        $active=true;
                        break;
                    }
                }
            }

            if($active==true){
                break;
            }
        }

        if($active==false){
            foreach($reports as $report){
                if(json_decode($report->imagen_creditos)!=null){
                    foreach(json_decode($report->imagen_creditos) as $credito){
                        if($credit_id==$credito->credito & $credito->dias_mora>=1){
                            $data_dia_sincronizado=[
                                "credito"=>$credit_id,
                                "dias_mora"=>$credito->dias_mora,
                                "monto"=>$credito->monto,
                                "saldo_capital"=>$credito->saldo_capital,
                                "interes"=>$credito->interes,
                                "mora"=>$credito->mora,
                                "seguro_desgravamen"=>$credito->seguro_desgravamen,
                                "gastos_cobranza"=>$credito->gastos_cobranza,
                                "gastos_judiciales"=>$credito->gastos_judiciales,
                                "otros"=>$credito->otros,
                                "rango"=>$credito->rango,
                                "con_gestion"=>$credito->con_gestion,
                                "agente"=>$credito->agente,
                                "fecha"=>$report->fecha
                            ];
    
                            $inactive=true;
                            break;
                        }
                    }
                }

                if($inactive==true){
                    break;
                }
            }
        }

        return [
            "status"=>$active,
            "inactive"=>$inactive,
            "data"=>$data_dia_sincronizado
        ];
    }
}
