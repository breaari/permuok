import { Icon } from "../../../ui/icons/Index";

export default function DevelopmentFormProgress({
  currentStep = 1,
  isEditMode = false,
}) {
  const isStepOne = currentStep === 1;

  return (
    <section className="bg-white p-5 sm:p-6 md:p-8 rounded-lg shadow-sm border border-slate-200">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="flex items-center gap-4">
          <div className="flex items-center justify-center w-10 h-10 rounded-full bg-slate-900 text-white font-bold">
            {isStepOne ? 1 : <Icon name="checkCircle" size={18} />}
          </div>

          <div>
            <p className="text-xs font-bold uppercase tracking-widest text-slate-500">
              Paso 1 de 2
            </p>
            <h1 className="text-lg sm:text-xl font-extrabold text-slate-900 -mt-0.5">
              {isEditMode ? "Editar desarrollo" : "Datos del desarrollo"}
            </h1>
          </div>
        </div>

        <div className="hidden md:block flex-1 mx-8 h-1 bg-slate-200 rounded-full overflow-hidden">
          <div
            className={`h-full bg-emerald-600 rounded-full transition-all duration-300 ${
              isStepOne ? "w-1/2" : "w-full"
            }`}
          />
        </div>

        <div
          className={`flex items-center gap-4 ${
            isStepOne ? "opacity-40" : ""
          }`}
        >
          <div
            className={`flex items-center justify-center w-10 h-10 rounded-full font-bold ${
              isStepOne
                ? "border-2 border-slate-300 text-slate-500"
                : "bg-slate-900 text-white"
            }`}
          >
            2
          </div>

          <div className="hidden sm:block">
            <p
              className={`text-xs font-bold uppercase tracking-widest ${
                isStepOne ? "text-slate-400" : "text-slate-500"
              }`}
            >
              Paso 2
            </p>
            <h2
              className={`text-sm font-bold ${
                isStepOne ? "text-slate-400" : "text-slate-900"
              }`}
            >
              Tipologías y amenities
            </h2>
          </div>
        </div>
      </div>
    </section>
  );
}