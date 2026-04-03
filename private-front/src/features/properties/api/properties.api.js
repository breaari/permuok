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

  images.forEach((item) => {
    if (item?.file) {
      formData.append("images[]", item.file);
    }
  });

  const res = await http.post(`/properties/${id}/images`, formData);
  return unwrap(res);
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