import AppRouter from '@/app/AppRouter'

/** Raiz del portal. `.ms03-portal` acota los estilos para convivir con el shell y los otros microfrontends. */
export default function App({ basename = '/' }: { basename?: string }) {
  return (
    <div className="ms03-portal">
      <AppRouter basename={basename} />
    </div>
  )
}
