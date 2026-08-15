import { http, unwrap } from "../../../api/http";

export async function createPropertyDraft(payload) {
  const res = await http.post("/properties", payload);
  return unwrap(res);
}

export async function updatePropertyDraft(id, payload) {
  const res = await http.patch(`/properties/${id}`, payload);
  return unwrap(res);
}

export async function savePropertyRequirements(id, payload) {
  const res = await http.put(`/properties/${id}/requirements`, payload);
  return unwrap(res);
}

export async function publishProperty(id) {
  const res = await http.post(`/properties/${id}/publish`, {});
  return unwrap(res);
}

export async function archiveProperty(id) {
  const res = await http.post(`/properties/${id}/archive`, {});
  return unwrap(res);
}

export async function uploadPropertyImages(id, images) {
  const formData = new FormData();

  images.forEach((item, index) => {
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

  const appendedFiles = formData.getAll("images[]");

  if (!appendedFiles.length) {
    throw new Error("No se encontraron archivos válidos para subir.");
  }

  const res = await http.post(`/properties/${id}/images`, formData);
  const data = unwrap(res);

  return data;
}

export async function reorderPropertyImages(id, images) {
  const res = await http.patch(`/properties/${id}/images/reorder`, {
    images,
  });
  return unwrap(res);
}

export async function deleteProperty(id) {
  const res = await http.post(`/properties/${id}/delete`, {});
  return unwrap(res);
}