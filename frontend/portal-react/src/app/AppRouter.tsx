import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { tieneRol } from '@/api/auth'
import { Alert } from '@/components/ui/Alert'
import { AppLayout } from '@/components/layout/AppLayout'
import Liquidaciones from '@/pages/liquidaciones/Liquidaciones'
import MetodosCobro from '@/pages/cobros/MetodosCobro'
import DetallePago from '@/pages/pagos/DetallePago'
import MisPagos from '@/pages/pagos/MisPagos'
import NuevoPago from '@/pages/pagos/NuevoPago'
import MisPromociones from '@/pages/promociones/MisPromociones'

export default function AppRouter({ basename = '/' }: { basename?: string }) {
  const anfitrion = tieneRol('ANFITRION')
  const cliente = tieneRol('CLIENTE')

  return (
    <BrowserRouter basename={basename}>
      <AppLayout>
        {!cliente && !anfitrion ? (
          <Alert tipo="error">Tu usuario no tiene rol de cliente ni de anfitrión. La administración está en otro portal.</Alert>
        ) : (
          <Routes>
            <Route path="/" element={<Navigate to="/pagos" replace />} />
            <Route path="/pagos" element={<MisPagos />} />
            <Route path="/pagos/nuevo" element={cliente ? <NuevoPago /> : <Navigate to="/pagos" replace />} />
            <Route path="/pagos/:id" element={<DetallePago />} />
            {anfitrion && <Route path="/promociones" element={<MisPromociones />} />}
            {anfitrion && <Route path="/metodos-cobro" element={<MetodosCobro />} />}
            {anfitrion && <Route path="/liquidaciones" element={<Liquidaciones />} />}
            <Route path="*" element={<Navigate to="/pagos" replace />} />
          </Routes>
        )}
      </AppLayout>
    </BrowserRouter>
  )
}
