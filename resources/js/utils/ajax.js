export default async function (url, data = {}, returnJson = true) {
    if (!data instanceof FormData) {
      let temp = new FormData();
  
      for (let key in data) {
        temp.append(key, data[key]);
      }
  
      data = temp;
    }
  
    try {
      const response = await fetch(url, {
        method: 'POST',
        body: data
      });
  
      if (returnJson) {
        const json = await response.json();
        return json;
      }
  
      return await response.text();
    } catch (error) {
      return {
        success: false,
        data: {
          message: error.message
        }
      };
    }
  }
  