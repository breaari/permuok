// import { useMemo } from "react";
// import { useNavigate } from "react-router-dom";
// import { useAuth } from "../auth/AuthContext";

// export default function AppHome() {
//   const { user, access, logout } = useAuth();
//   const nav = useNavigate();

//   const role = Number(user?.role || 0);
//   const isAdmin = role === 1;

//   const actions = useMemo(() => {
//     // acciones “mínimas” para cerrar etapa funcional
//     if (isAdmin) {
//       return [
//         {
//           label: "Panel Admin (revisiones)",
//           desc: "Aprobar / rechazar inmobiliarias",
//           onClick: () => nav("/admin", { replace: false }),
//           primary: true,
//         },
//       ];
//     }

//     const list = [
//       {
//         label: "Mis datos (Onboarding)",
//         desc: "Completar/editar perfil y matrículas",
//         onClick: () => nav("/onboarding", { replace: false }),
//         primary: false,
//       },
//     ];

//     switch (access?.level) {
//       case "real_estate_not_linked":
//       case "real_estate_draft":
//         list.unshift({
//           label: "Completar perfil",
//           desc: "Requerido para pedir revisión",
//           onClick: () => nav("/onboarding", { replace: false }),
//           primary: true,
//         });
//         break;

//       case "real_estate_review":
//         list.unshift({
//           label: "Ver estado de revisión",
//           desc: "Esperando aprobación",
//           onClick: () => nav("/status/review", { replace: false }),
//           primary: true,
//         });
//         break;

//       case "real_estate_unpaid":
//         list.unshift({
//           label: "Activar membresía",
//           desc: "Elegir plan y pagar",
//           onClick: () => nav("/billing", { replace: false }),
//           primary: true,
//         });
//         break;

//       case "real_estate_active":
//       default:
//         list.unshift({
//           label: "Ir a la app",
//           desc: "Usar la plataforma",
//           onClick: () => nav("/app", { replace: false }),
//           primary: true,
//         });
//         break;
//     }

//     return list;
//   }, [access?.level, isAdmin, nav]);

//   function levelLabel(level) {
//     switch (level) {
//       case "real_estate_active":
//         return "Cuenta activa";
//       case "real_estate_unpaid":
//         return "Falta pago";
//       case "real_estate_review":
//         return "En revisión";
//       case "real_estate_draft":
//         return "Perfil incompleto";
//       case "real_estate_not_linked":
//         return "Sin inmobiliaria vinculada";
//       default:
//         return level ?? "—";
//     }
//   }

//   return (
//     <div className="min-h-screen p-6">
//       <div className="mx-auto max-w-3xl space-y-6">
//         <div className="flex items-center justify-between gap-3">
//           <h1 className="text-2xl font-semibold">{isAdmin ? "Panel Admin" : "Panel"}</h1>

//           <button onClick={logout} className="rounded-lg border px-4 py-2 text-sm">
//             Cerrar sesión
//           </button>
//         </div>

//         <div className="rounded-2xl border p-5">
//           <h2 className="font-medium">Usuario</h2>

//           <div className="mt-3 space-y-1 text-sm">
//             <div>
//               <b>Email:</b> {user?.email || "—"}
//             </div>
//             <div>
//               <b>Rol:</b> {user?.role ?? "—"} {isAdmin ? "(admin)" : ""}
//             </div>
//             <div>
//               <b>Real estate ID:</b> {user?.real_estate_id ?? "—"}
//             </div>
//           </div>
//         </div>

//         {!isAdmin && (
//           <div className="rounded-2xl border p-5">
//             <h2 className="font-medium">Estado de cuenta</h2>

//             <div className="mt-3 text-sm">
//               <div className="font-medium">{levelLabel(access?.level)}</div>

//               {access?.limits && (
//                 <div className="mt-2 text-gray-600">
//                   Agentes: {access.limits.agents} | Inversores: {access.limits.investors}
//                 </div>
//               )}

//               {access?.features && (
//                 <div className="mt-2 text-gray-600">
//                   Publicar proyectos: {access.features.publish_projects ? "Sí" : "No"}
//                 </div>
//               )}
//             </div>
//           </div>
//         )}

//         <div className="rounded-2xl border p-5">
//           <h2 className="font-medium">Acciones</h2>

//           <div className="mt-4 grid gap-3">
//             {actions.map((a, idx) => (
//               <button
//                 key={idx}
//                 onClick={a.onClick}
//                 className={
//                   a.primary
//                     ? "w-full rounded-lg bg-black px-4 py-2 text-left text-white"
//                     : "w-full rounded-lg border px-4 py-2 text-left"
//                 }
//               >
//                 <div className={a.primary ? "font-medium" : "font-medium"}>{a.label}</div>
//                 {a.desc && (
//                   <div className={a.primary ? "text-xs text-white/80" : "text-xs text-gray-600"}>
//                     {a.desc}
//                   </div>
//                 )}
//               </button>
//             ))}
//           </div>

//           {!isAdmin && (
//             <div className="mt-4 text-sm text-gray-600">
//               {access?.level === "real_estate_active" && <div>✔ Podés usar la plataforma</div>}
//               {access?.level === "real_estate_unpaid" && <div>⚠ Necesitás activar la membresía</div>}
//               {access?.level === "real_estate_review" && <div>⌛ Tu perfil está en revisión</div>}
//               {(access?.level === "real_estate_draft" ||
//                 access?.level === "real_estate_not_linked") && (
//                 <div>✏ Completá tu perfil para solicitar revisión</div>
//               )}
//             </div>
//           )}
//         </div>
//       </div>
//     </div>
//   );
// }

export default function AppHome() {
  return (
    <div className="min-h-screen p-6">
      <div className="mx-auto max-w-3xl space-y-6">
        <h1 className="text-2xl font-semibold">Bienvenido a Permuok</h1>
        <div className="rounded-2xl border p-5">
          <p className="text-gray-700">
            Esta es la página de inicio de la app. Desde aquí podés navegar a las distintas secciones usando el menú lateral.
          </p>  
        </div>
      </div>
    </div>
  );
}