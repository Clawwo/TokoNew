<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Penjualan;
use App\Models\DetailPenjualan;
use App\Models\Pelanggan;
use App\Models\Barang;
use Illuminate\Support\Facades\DB;

class PenjualanController extends Controller
{
    public function create()
    {
        $pelanggan = Pelanggan::all();
        $barang = Barang::all();
        return view('kasir.transaksi', compact('pelanggan', 'barang'));
    }

    public function getBarang($id_barang)
    {
        $barang = Barang::find($id_barang);
        return response()->json($barang);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // Cek apakah pelanggan terdaftar
            $pelanggan = Pelanggan::find($request->id_pelanggan);
            $isMember = $pelanggan ? true : false;

            // Simpan transaksi
            $penjualan = Penjualan::create([
                'id_pelanggan' => $request->id_pelanggan,
                'tgl_transaksi' => now(),
                'total_transaksi' => 0, // Akan diperbarui nanti
            ]);

            $totalHarga = 0;

            foreach ($request->barang as $item) {
                $barang = Barang::find($item['id_barang']);

                if ($barang && $barang->stock >= $item['jml_barang']) {
                    $subtotal = $barang->harga_barang * $item['jml_barang'];
                    $totalHarga += $subtotal;

                    // Simpan detail penjualan
                    DetailPenjualan::create([
                        'id_transaksi' => $penjualan->id_transaksi,
                        'id_barang' => $barang->id_barang,
                        'jml_barang' => $item['jml_barang'],
                        'harga_satuan' => $barang->harga_barang,
                    ]);

                    // Kurangi stok barang
                    $barang->stock -= $item['jml_barang'];
                    $barang->save();
                } else {
                    // Jika stok tidak cukup
                    DB::rollBack();
                    return back()->with('error', 'Stok barang tidak cukup.');
                }
            }

            // Jika pelanggan terdaftar, berikan diskon 10%
            $diskon = $isMember ? ($totalHarga * 0.1) : 0;
            $totalAkhir = $totalHarga - $diskon;

            // Update total transaksi
            $penjualan->update(['total_transaksi' => $totalAkhir]);

            DB::commit();

            return redirect()->route('transaksi.create')->with('success', 'Transaksi berhasil! Total: ' . number_format($totalAkhir, 2) . ($isMember ? ' (Diskon 10%)' : ' (Tanpa Diskon)'));
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
