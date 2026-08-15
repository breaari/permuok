import { http, unwrap } from "../../../api/http";

function normalizeDevelopmentError(error) {
  const raw =
    error?.data?.message ||
    error?.data?.error ||
    error?.message ||
    error?.response?.data ||
    error?.data ||
    "";

  const text = String(raw);

  if (text.includes("Tu plan no permite publicar desarrollos")) {
    throw new Error("Tu plan no permite publicar desarrollos.");
  }

  if (text.includes("La membresía de la inmobiliaria no está activa")) {
    throw new Error("La membresía de la inmobiliaria no está activa.");
  }

  if (text.includes("La inmobiliaria no tiene una membresía activa")) {
    throw new Error("La inmobiliaria no tiene una membresía activa.");
  }

  if (text.includes("La membresía de la inmobiliaria está vencida")) {
    throw new Error("La membresía de la inmobiliaria está vencida.");
  }

  throw error;
}

async function safeRequest(requestFn) {
  try {
    const res = await requestFn();
    return unwrap(res);
  } catch (error) {
    normalizeDevelopmentError(error);
  }
}

export async function createDevelopmentDraft(payload) {
  return safeRequest(() => http.post("/developments", payload));
}

export async function updateDevelopmentDraft(id, payload) {
  return safeRequest(() => http.patch(`/developments/${id}`, payload));
}

export async function getDevelopmentDetail(id, explore = false) {
  const path = explore ? `/explore/developments/${id}` : `/developments/${id}`;
  return safeRequest(() => http.get(path));
}

export async function listMyDevelopments(params = {}) {
  return safeRequest(() => http.get("/developments", { params }));
}

export async function listExploreDevelopments(params = {}) {
  return safeRequest(() => http.get("/explore/developments", { params }));
}

export async function publishDevelopment(id) {
  return safeRequest(() => http.post(`/developments/${id}/publish`, {}));
}

export async function pauseDevelopment(id) {
  return safeRequest(() => http.post(`/developments/${id}/pause`, {}));
}

export async function archiveDevelopment(id) {
  return safeRequest(() => http.post(`/developments/${id}/archive`, {}));
}

export async function closeDevelopment(id) {
  return safeRequest(() => http.post(`/developments/${id}/close`, {}));
}

export async function deleteDevelopment(id) {
  return safeRequest(() => http.post(`/developments/${id}/delete`, {}));
}

export async function uploadDevelopmentImages(id, images) {
  const formData = new FormData();

  images.forEach((item) => {
    const file =
      item instanceof File
        ? item
        : item?.file instanceof File
          ? item.file
          : null;

    if (file) {
      formData.append("images[]", file);
    }
  });

  const appended = formData.getAll("images[]").length;
  if (!appended) {
    throw new Error("No se encontraron archivos válidos para subir.");
  }

  return safeRequest(() => http.post(`/developments/${id}/images`, formData));
}

export async function reorderDevelopmentImages(id, images) {
  return safeRequest(() =>
    http.patch(`/developments/${id}/images/reorder`, { images }),
  );
}

export async function deleteDevelopmentImage(imageId) {
  return safeRequest(() => http.del(`/developments/images/${imageId}`));
}

export async function listDevelopmentUnitTypes(id) {
  return safeRequest(() => http.get(`/developments/${id}/unit-types`));
}

export async function createDevelopmentUnitType(id, payload) {
  return safeRequest(() =>
    http.post(`/developments/${id}/unit-types`, payload),
  );
}

export async function updateDevelopmentUnitType(unitTypeId, payload) {
  return safeRequest(() =>
    http.patch(`/developments/unit-types/${unitTypeId}`, payload),
  );
}

export async function deleteDevelopmentUnitType(unitTypeId) {
  return safeRequest(() => http.del(`/developments/unit-types/${unitTypeId}`));
}

export async function listDevelopmentAmenities(id) {
  return safeRequest(() => http.get(`/developments/${id}/amenities`));
}

export async function replaceDevelopmentAmenities(id, amenities) {
  return safeRequest(() =>
    http.put(`/developments/${id}/amenities`, { amenities }),
  );
}
