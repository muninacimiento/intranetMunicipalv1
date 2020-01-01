<?php


/*
 *  JFuentealba @itux
 *  created at October 24, 2019 - 11:25 am
 *  updated at December 23, 2019 - 1:29 pm
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/* Invocamos la clase Carbon para trabajar con fechas */
use Carbon\Carbon;

/* Invocamos el modelo de la Entidad */
use App\Solicitud;

/* Invocamos el modelo de la Entidad DetalleSolicitud*/
use App\DetailSolicitud;

/* Invocamos el modelo de la Entidad Movimiento de la Solicitud*/
use App\MoveSolicitud;

/* Invocamos el modelo de la Entidad Actividad*/
use App\Activity;

/* Invocamos el modelo de la Entidad Product*/
use App\Product;

use App\User;
use DB;

class SCM_AdminSolicitudController extends Controller
{
    
	public function index(Request $request)
    {

         /*
         * Definimos variable que contendrá la fecha actual del sistema
         */
        $dateCarbon = Carbon::now()->locale('es')->isoFormat('dddd D, MMMM YYYY');

        /* Declaramos la variable que contendrá todos los permisos existentes en la base de datos */
        $solicituds = DB::table('solicituds')
                    ->join('status_solicituds', 'solicituds.estado_id', '=', 'status_solicituds.id')
                    ->select('solicituds.*', 'status_solicituds.estado')
                    ->orderBy('solicituds.id', 'desc')
                    ->get();

        //dd($solicituds);

        /* Retornamos a la vista los resultados psanadolos por parametros */
        return view('siscom.admin.index', ['solicituds' => $solicituds, 'dateCarbon' => $dateCarbon]);
    }

     public function show($id)
    {
         /*
         * Declaramos un Objeto para obtener acceso a los datos de la tabla DetalleSolicitud
         */
        $detalleSolicitud = DB::table('detail_solicituds')
                    ->join('products', 'detail_solicituds.product_id', 'products.id')
                    ->join('solicituds', 'detail_solicituds.solicitud_id', '=', 'solicituds.id')
                    ->select('detail_solicituds.*', 'products.name as Producto', DB::raw('(detail_solicituds.cantidad * detail_solicituds.valorUnitario) as SubTotal'))
                     ->where('solicituds.id', '=', $id) //Revisar la vista y el envio de los datos a la tabla de Detalle de la Solicitud
                    ->get();

        $products = DB::table('products as productos')
                    ->select(DB::raw('CONCAT(productos.id, " ) ", productos.name) as Producto'), 'productos.id')
                    ->get();

        $solicitud = DB::table('solicituds')
                   ->join('users', 'solicituds.user_id', '=', 'users.id')
                   ->join('status_solicituds', 'solicituds.estado_id', '=', 'status_solicituds.id')
                   ->select('solicituds.*', 'users.name as nameUser', 'status_solicituds.estado')
                   ->where('solicituds.id', '=', $id)
                   ->first();

        $move = DB::table('move_solicituds') 
                ->join('status_solicituds', 'move_solicituds.estadoSolicitud_id', 'status_solicituds.id')               
                ->join('users', 'move_solicituds.user_id', 'users.id')
                ->select('status_solicituds.estado as status', 'users.name as name', 'move_solicituds.created_at as date')
                ->where('move_solicituds.solicitud_id', '=', $id)
                ->get();

        //dd($move);

        return view('siscom.admin.show', compact('solicitud', 'products', 'detalleSolicitud', 'move'));

    }

    public function update(Request $request, $id)
    {
       // Actualizar ENCABEZADO Solicitud
        if ($request->flag == 'Recepcionar') {

            try {

                DB::beginTransaction();

                    $solicitud = Solicitud::findOrFail($id);
                    $solicitud->iddoc                       = $request->iddoc;
                    $solicitud->estado_id                   = 3;

                    $solicitud->save();

                    //Guardamos los datos de Movimientos de la Solicitud
                    $move = new MoveSolicitud;
                    $move->solicitud_id                     = $solicitud->id;
                    $move->estadoSolicitud_id               = 3;
                    $move->fecha                            = $solicitud->updated_at;
                    $move->user_id                          = Auth::user()->id;

                    $move->save(); //Guardamos el Movimiento de la Solicitud    

                DB::commit();
                
            } catch (Exception $e) {

                DB::rollback();
                
            }

            return redirect('/siscom/admin')->with('info', 'Solicitud Recepcionada con éxito !');
        }

        // Asignar Solicitud
        else if ($request->flag == 'Asignar') {

            try {

                DB::beginTransaction();

                    $solicitud = Solicitud::findOrFail($id);
                    $solicitud->compradorTitular            = $request->compradorTitular;
                    $solicitud->estado_id                   = 4;

                    //dd($solicitud);

                    $solicitud->save(); //Guardamos la Solicitud

                     //Guardamos los datos de Movimientos de la Solicitud
                    $move = new MoveSolicitud;
                    $move->solicitud_id                     = $solicitud->id;
                    $move->estadoSolicitud_id               = 4;
                    $move->fecha                            = $solicitud->updated_at;
                    $move->user_id                          = Auth::user()->id;

                    $move->save(); //Guardamos el Movimiento de la Solicitud    

                DB::commit();
                
            } catch (Exception $e) {

                DB::rollback();
                
            }

            return redirect('/siscom/admin')->with('info', 'Solicitud Asignada con éxito !');
        }    

        // Confirmar Solicitud
        else if ($request->flag == 'Confirmar') {

            $solicitud = Solicitud::findOrFail($id);

            $solicitud->user_id                     = Auth::user()->id;
            $solicitud->estado_id                   = 2;
            $solicitud->total                       = $request->totalSolicitud;

            //dd($solicitud);

            $solicitud->save(); //Guardamos la Solicitud

            //Guardamos los datos de Movimientos de la Solicitud
                    $move = new MoveSolicitud;
                    $move->solicitud_id                     = $solicitud->id;
                    $move->estadoSolicitud_id               = 2;
                    $move->fecha                            = $solicitud->updated_at;
                    $move->user_id                          = Auth::user()->id;

                    $move->save(); //Guardamos el Movimiento de la Solicitud    

            return redirect('/siscom/admin')->with('info', 'Solicitud Confirmada con éxito !');
        }   

        // Actualizar Actividad
        else if ($request->flag == 'Actividad') {

            $solicitud = Solicitud::findOrFail($id);

            $solicitud->user_id                     = Auth::user()->id;
            $solicitud->nombreActividad             = $request->nombreActividad;
            $solicitud->fechaActividad              = $request->fechaActividad;
            $solicitud->horaActividad               = $request->horaActividad;
            $solicitud->lugarActividad              = $request->lugarActividad;
            $solicitud->objetivoActividad           = $request->objetivoActividad;
            $solicitud->descripcionActividad        = $request->descripcionActividad;
            $solicitud->participantesActividad      = $request->participantesActividad;
            $solicitud->cuentaPresupuestaria        = $request->cuentaPresupuestaria;
            $solicitud->cuentaComplementaria        = $request->cuentaComplementaria;
            $solicitud->obsActividad                = $request->obsActividad;

            //dd($solicitud);

            $solicitud->save(); //Guardamos la Solicitud

            return back();

        }

        // Anular Solicitud
        else if ($request->flag == 'Anular') {

            try {

                DB::beginTransaction();

                    $solicitud = Solicitud::findOrFail($id);
                    $solicitud->user_id                     = Auth::user()->id;
                    $solicitud->motivoAnulacion            = $request->motivoAnulacion;
                    $solicitud->estado_id                  = 11;

                    //dd($solicitud);

                    $solicitud->save(); //Guardamos la Solicitud

                    //Guardamos los datos de Movimientos de la Solicitud
                    $move = new MoveSolicitud;
                    $move->solicitud_id                     = $solicitud->id;
                    $move->estadoSolicitud_id               = 11;
                    $move->fecha                            = $solicitud->updated_at;
                    $move->user_id                          = Auth::user()->id;

                    $move->save(); //Guardamos el Movimiento de la Solicitud    

                DB::commit();
                
            } catch (Exception $e) {

                db::rollback();
                
            }

            return redirect('/siscom/admin')->with('info', 'Solicitud Anulada con éxito !');
        }    

        // Actualizamos Producto del Detalle de la Solicitud
        else if ($request->flag == 'UpdateProducts') {

            $detalleSolicitud = DetailSolicitud::findOrFail($id);

            $detalleSolicitud->cantidad             = $request->Cantidad;
            $detalleSolicitud->especificacion       = $request->Especificacion;
            $detalleSolicitud->valorUnitario        = $request->ValorUnitario;        

            //dd($solicitud);

            $detalleSolicitud->save(); //Guardamos la Solicitud

            return back();

        }
        // Eliminamos el Producto del Detalle de la Solicitud
        else if ($request->flag == 'DeleteProduct') {

            $deleteProduct = DetailSolicitud::findOrFail($id);

            //dd($solicitud);

            $deleteProduct->delete(); //Guardamos la Solicitud

            return back();

        }    

    }

}