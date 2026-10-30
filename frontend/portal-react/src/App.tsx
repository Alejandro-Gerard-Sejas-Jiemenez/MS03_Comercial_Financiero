import { BrowserRouter, Navigate, NavLink, Route, Routes } from 'react-router-dom'
import { cerrarSesion, nombreUsuario, roles, tieneRol } from './auth'
import DetallePago from './pages/DetallePago'
import Liquidaciones from './pages/Liquidaciones'
import MetodosCobro from './pages/MetodosCobro'
import MisPagos from './pages/MisPagos'
import MisPromociones from './pages/MisPromociones'
import NuevoPago from './pages/NuevoPago'

export default function App({ basename = '/' }: { basename?: string }) {
  const anfitrion = tieneRol('ANFITRION')
  const cliente = tieneRol('CLIENTE')

  return (
    <BrowserRouter basename={basename}>
      <div className="portal">
        <header className="barra">
          <strong>Alójate · Comercial</strong>
          <nav>
            {(cliente || anfitrion) && <NavLink to="/pagos">Pagos</NavLink>}
            {anfitrion && <NavLink to="/promociones">Promociones</NavLink>}
            {anfitrion && <NavLink to="/metodos-cobro">Cobros</NavLink>}
            {anfitrion && <NavLink to="/liquidaciones">Liquidaciones</NavLink>}
          </nav>
          <span className="usuario">
            {nombreUsuario()} · {roles().join(', ') || 'sin rol'}
            <button className="secundario" onClick={cerrarSesion}>
              Salir
            </button>
          </span>
        </header>
        <main>
          {!cliente && !anfitrion ? (
            <p className="aviso error">Tu usuario no tiene rol de cliente ni de anfitrión. La administración está en otro portal.</p>
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
        </main>
      </div>
    </BrowserRouter>
  )
}
